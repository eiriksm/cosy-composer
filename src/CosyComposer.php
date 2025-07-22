<?php

namespace eiriksm\CosyComposer;

use eiriksm\CosyComposer\Exceptions\ChdirException;
use eiriksm\CosyComposer\Exceptions\GitCloneException;
use eiriksm\CosyComposer\Exceptions\OutsideProcessingHoursException;
use eiriksm\CosyComposer\ListFilterer\DevDepsOnlyFilterer;
use eiriksm\CosyComposer\ListFilterer\IndirectWithDirectFilterer;
use eiriksm\CosyComposer\Providers\Bitbucket;
use eiriksm\CosyComposer\Providers\NamedPrs;
use eiriksm\CosyComposer\Providers\PublicGithubWrapper;
use eiriksm\CosyComposer\Updater\IndividualUpdater;
use GuzzleHttp\Psr7\Request;
use Http\Adapter\Guzzle7\Client as GuzzleClient;
use Http\Client\HttpClient;
use League\Flysystem\FilesystemAdapter;
use Symfony\Component\Process\Process;
use Violinist\AllowListHandler\AllowListHandler;
use Violinist\ComposerLockData\ComposerLockData;
use Violinist\ComposerUpdater\Exception\NotUpdatedException;
use Violinist\Config\Config;
use eiriksm\ViolinistMessages\ViolinistMessages;
use Github\Client;
use Github\Exception\RuntimeException;
use Github\Exception\ValidationFailedException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Psr\Log\LoggerInterface;
use Violinist\RepoAndTokenToCloneUrl\ToCloneUrl;
use Violinist\Slug\Slug;
use Violinist\TimeFrameHandler\Handler;
use Wa72\SimpleLogger\ArrayLogger;

class CosyComposer
{
    use ComposerInstallTrait;
    use PrCounterTrait;
    use GitCommandsTrait;
    use SlugAwareTrait;
    use TokenAwareTrait;
    use AssigneesAllowedTrait;
    use TemporaryDirectoryAwareTrait;

    const UPDATE_ALL = 'update_all';

    const UPDATE_INDIVIDUAL = 'update_individual';

    private $urlArray;

    /**
     * @var bool|string
     */
    private $lockFileContents;

    /**
     * @var ProviderFactory
     */
    protected $providerFactory;

    /**
     * @var \eiriksm\CosyComposer\CommandExecuter
     */
    protected $executer;

    /**
     * @var ComposerFileGetter
     */
    protected $composerGetter;

    /**
     * @var string
     */
    protected $cwd;

    /**
     * @var string
     */
    private $forkUser;

    /**
     * @var ViolinistMessages
     */
    private $messageFactory;

    /**
     * @var string
     */
    protected $composerJsonDir;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var null|\Violinist\ProjectData\ProjectData
     */
    protected $project;

    /**
     * @var HttpClient
     */
    protected $httpClient;

    /**
     * @var string
     */
    protected $tokenUrl;

    /**
     * @var bool
     */
    private $isPrivate = false;

    /**
     * @var SecurityCheckerFactory
     */
    private $checkerFactory;

    /**
     * @var ProviderInterface
     */
    private $client;

    /**
     * @var ProviderInterface
     */
    private $privateClient;

    /**
     * @var string
     */
    private $hostName;

    /**
     * @var PrParamsCreator
     */
    private $prParamsCreator;

    /**
     * @param array $tokens
     */
    public function setTokens(array $tokens)
    {
        $this->tokens = $tokens;
    }

    /**
     * @return SecurityCheckerFactory
     */
    public function getCheckerFactory()
    {
        return $this->checkerFactory;
    }

    /**
     * @param string $tokenUrl
     */
    public function setTokenUrl($tokenUrl)
    {
        $this->tokenUrl = $tokenUrl;
    }

    /**
     * @param \Violinist\ProjectData\ProjectData|null $project
     */
    public function setProject($project)
    {
        $this->project = $project;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        if (!$this->logger instanceof LoggerInterface) {
            $this->logger = new ArrayLogger();
        }
        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return HttpClient
     */
    public function getHttpClient()
    {
        if (!$this->httpClient instanceof HttpClient) {
            $this->httpClient = new GuzzleClient();
        }
        return $this->httpClient;
    }

    /**
     * @param HttpClient $httpClient
     */
    public function setHttpClient(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * @return string
     */
    public function getCwd()
    {
        return $this->cwd;
    }

    /**
     * @param \eiriksm\CosyComposer\CommandExecuter $executer
     */
    public function setExecuter($executer)
    {
        $this->executer = $executer;
    }

    /**
     * @param ProviderFactory $providerFactory
     */
    public function setProviderFactory(ProviderFactory $providerFactory)
    {
        $this->providerFactory = $providerFactory;
    }


    /**
     * CosyComposer constructor.
     */
    public function __construct(CommandExecuter $executer)
    {
        $tmpdir_name = uniqid();
        $this->setTmpDir(sprintf('/tmp/%s', $tmpdir_name));
        $this->messageFactory = new ViolinistMessages();
        $this->executer = $executer;
        $this->checkerFactory = new SecurityCheckerFactory();
    }

    public function setUrl($url = null)
    {
        if (!empty($url)) {
            $url = preg_replace('/\.git$/', '', $url);
        }
        $slug_url_obj = parse_url($url);
        if (empty($slug_url_obj['port']) && !empty($slug_url_obj['scheme'])) {
            // Set it based on scheme.
            switch ($slug_url_obj['scheme']) {
                case 'http':
                    $slug_url_obj['port'] = 80;
                    break;

                case 'https':
                    $slug_url_obj['port'] = 443;
                    break;
            }
        }
        $this->urlArray = $slug_url_obj;
        $providers = Slug::getSupportedProviders();
        if (!empty($slug_url_obj['host'])) {
            $providers = array_merge($providers, [$slug_url_obj['host']]);
        }
        $this->setSlug(Slug::createFromUrlAndSupportedProviders($url, $providers));
    }

    /**
     * @deprecated Use ::setAuthentication instead.
     *
     * @see CosyComposer::setAuthentication
     */
    public function setGithubAuth($user, $pass)
    {
        $this->setAuthentication($user);
    }

    /**
     * @deprecated use ::setAuthentication instead.
     */
    public function setUserToken($user_token)
    {
        $this->setAuthentication($user_token);
    }

  /**
   * Set a user to fork to.
   *
   * @param string $user
   */
    public function setForkUser($user)
    {
        $this->forkUser = $user;
    }

    protected function handleTimeIntervalSetting(Config $config)
    {
        if (Handler::isAllowed($config)) {
            return;
        }
        throw new OutsideProcessingHoursException('Current hour is inside timeframe disallowed');
    }

    public function handleDrupalContribSa($cdata)
    {
        if (!getenv('DRUPAL_CONTRIB_SA_PATH')) {
            return;
        }
        $symfony_dir = sprintf('%s/.symfony/cache/security-advisories/drupal', getenv('HOME'));
        if (!file_exists($symfony_dir)) {
            $mkdir = $this->execCommand(['mkdir', '-p', $symfony_dir]);
            if ($mkdir) {
                return;
            }
        }
        $contrib_sa_dir = getenv('DRUPAL_CONTRIB_SA_PATH');
        if (empty($cdata->repositories)) {
            return;
        }
        foreach ($cdata->repositories as $repository) {
            if (empty($repository->url)) {
                continue;
            }
            if ($repository->url === 'https://packages.drupal.org/8') {
                $process = Process::fromShellCommandline('rsync -aq ' . sprintf('%s/sa_yaml/8/drupal/*', $contrib_sa_dir) .  " $symfony_dir/");
                $process->run();
            }
            if ($repository->url === 'https://packages.drupal.org/7') {
                $process = Process::fromShellCommandline('rsync -aq ' . sprintf('%s/sa_yaml/7/drupal/*', $contrib_sa_dir) .  " $symfony_dir/");
                $process->run();
            }
        }
    }

    /**
     * Export things.
     */
    protected function exportEnvVars()
    {
        if (!$this->project) {
            return;
        }
        $env = $this->project->getEnvString();
        if (empty($env)) {
            return;
        }
        // One per line.
        $env_array = preg_split("/\r\n|\n|\r/", $env);
        if (empty($env_array)) {
            return;
        }
        foreach ($env_array as $env_string) {
            if (empty($env_string)) {
                continue;
            }
            $env_parts = explode('=', $env_string, 2);
            if (count($env_parts) != 2) {
                continue;
            }
            // We do not allow to override ENV vars.
            $key = $env_parts[0];
            $existing_env = getenv($key);
            if ($existing_env) {
                $this->getLogger()->log('info', new Message("The ENV variable $key was skipped because it exists and can not be overwritten"));
                continue;
            }
            $value = $env_parts[1];
            $this->getLogger()->log('info', new Message("Exporting ENV variable $key: $value"));
            putenv($env_string);
            $_ENV[$key] = $value;
        }
    }

    protected function closeOutdatedPrsForPackage($package_name, $current_version, Config $config, $pr_id, NamedPrs $prs_named_obj, $default_branch)
    {
        $fake_item = (object) [
            'name' => $package_name,
            'version' => $current_version,
            'latest' => '',
        ];
        $branch_name_prefix = Helpers::createBranchName($fake_item, false, $config);
        $prs_for_package = $prs_named_obj->getPrsFromPackage($package_name);
        foreach ($prs_for_package as $pr) {
            if (!empty($pr["base"]["ref"])) {
                // The base ref should be what we are actually using for merge requests.
                if ($pr["base"]["ref"] != $default_branch) {
                    continue;
                }
            }
            // We don't want to close this exact PR do we?
            if ((string) $pr['number'] === (string) $pr_id) {
                continue;
            }
            $comment = $this->messageFactory->getPullRequestClosedMessage($pr_id);
            $pr_number = $pr['number'];
            $this->getLogger()->log('info', new Message("Trying to close PR number $pr_number since it has been superseded by $pr_id"));
            try {
                $this->getPrClient()->closePullRequestWithComment($this->slug, $pr_number, $comment);
                $this->getLogger()->log('info', new Message("Successfully closed PR $pr_number"));
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                $this->getLogger()->log('error', new Message("Caught an exception trying to close pr $pr_number. The message was '$msg'"));
            }
        }
    }

    public function setViolinistHostname(string $hostname)
    {
        $this->hostName = $hostname;
    }

    public function getLocalAdapterForTempDir(string $directory) : FilesystemAdapter
    {
        $this->setTmpDir($directory);
        $composer_json_dir = $this->tmpDir;
        if ($this->project && $this->project->getComposerJsonDir()) {
            $composer_json_dir = sprintf('%s/%s', $this->tmpDir, $this->project->getComposerJsonDir());
        }
        $this->composerJsonDir = $composer_json_dir;
        return new LocalFilesystemAdapter($this->composerJsonDir);
    }

    /**
     * @throws \eiriksm\CosyComposer\Exceptions\ChdirException
     * @throws \eiriksm\CosyComposer\Exceptions\GitCloneException
     * @throws \InvalidArgumentException
     * @throws \Exception
     * @throws \Throwable
     */
    public function run()
    {
        // Always start by making sure the .ssh directory exists.
        $directory = sprintf('%s/.ssh', getenv('HOME'));
        if (!file_exists($directory)) {
            if (!@mkdir($directory, 0700) && !is_dir($directory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $directory));
            }
        }
        // Export the environment variables if needed.
        $this->exportEnvVars();
        if ($this->hostName) {
            $this->log(sprintf('Running update check on %s', $this->hostName));
        }
        if (!empty($_SERVER['violinist_revision'])) {
            $this->log(sprintf('Queue starter revision %s', $_SERVER['violinist_revision']));
        }
        if (!empty($_SERVER['queue_runner_revision'])) {
            $this->log(sprintf('Queue runner revision %s', $_SERVER['queue_runner_revision']));
        }
        // Support an alternate composer version based on env var.
        if (!empty($_ENV['ALTERNATE_COMPOSER_PATH'])) {
            $allow_list = [
                '/usr/local/bin/composer22',
            ];
            if (!in_array($_ENV['ALTERNATE_COMPOSER_PATH'], $allow_list)) {
                throw new \InvalidArgumentException('The alternate composer path is not allowed');
            }
            $this->log('Trying to use composer from ' . $_ENV['ALTERNATE_COMPOSER_PATH']);
            if (file_exists('/usr/local/bin/composer')) {
                rename('/usr/local/bin/composer', '/usr/local/bin/composer.bak');
            }
            copy($_ENV['ALTERNATE_COMPOSER_PATH'], '/usr/local/bin/composer');
            chmod('/usr/local/bin/composer', 0755);
        }
        // Try to get the php version as well.
        $this->execCommand(['php', '--version']);
        $this->log($this->getLastStdOut());
        // Try to get the composer version as well.
        $this->execCommand(['composer', '--version']);
        $this->log($this->getLastStdOut());
        $this->log(sprintf('Starting update check for %s', $this->slug->getSlug()));
        $user_name = $this->slug->getUserName();
        $user_repo = $this->slug->getUserRepo();
        $hostname = $this->slug->getProvider();
        $url = null;
        // Make sure we accept the fingerprint of whatever we are cloning.
        $this->execCommand(['ssh-keyscan', '-t', 'rsa', $hostname, '>>', '~/.ssh/known_hosts']);
        if (!empty($_SERVER['private_key'])) {
            $this->log('Checking for existing private key');
            $filename = "$directory/id_rsa";
            if (!file_exists($filename)) {
                $this->log('Installing private key');
                file_put_contents($filename, $_SERVER['private_key']);
                $this->execCommand(['chmod', '600', $filename], false);
            }
        }
        $is_bitbucket = false;
        $bitbucket_user = null;
        $url = ToCloneUrl::fromRepoAndToken($this->slug->getUrl(), $this->userToken);
        switch ($hostname) {
            case 'bitbucket.org':
                $is_bitbucket = true;
                if (Bitbucket::tokenIndicatesUserAppPassword($this->userToken)) {
                    // The username will now be the thing before the colon.
                    [$bitbucket_user, $this->userToken] = explode(':', $this->userToken);
                }
                break;

            default:
                // Use the upstream package for this.
                break;
        }
        $urls = [
            $url,
        ];
        // We also want to check what happens if we append .git to the URL. This can be a problem in newer
        // versions of git, that git does not accept redirects.
        $length = strlen('.git');
        $ends_with_git = substr($url, -$length) === '.git';
        if (!$ends_with_git) {
            $urls[] = "$url.git";
        }
        $this->log('Cloning repository');
        foreach ($urls as $url) {
            $clone_result = $this->execCommand(['git', 'clone', '--depth=1', $url, $this->tmpDir], false, 240);
            if (!$clone_result) {
                break;
            }
        }
        if ($clone_result) {
            // We had a problem.
            $this->log($this->getLastStdOut());
            $this->log($this->getLastStdErr());
            throw new GitCloneException('Problem with the execCommand git clone. Exit code was ' . $clone_result);
        }
        $this->log('Repository cloned');
        $local_adapter = $this->getLocalAdapterForTempDir($this->tmpDir);
        $this->chdir($this->composerJsonDir);
        $uses_config_branch = false;
        if (!empty($_ENV['config_branch'])) {
            $uses_config_branch = true;
            $config_branch = $_ENV['config_branch'];
            $this->log('Changing to config branch: ' . $config_branch);
            $tmpdir = sprintf('/tmp/%s', uniqid('', true));
            $clone_result = $this->execCommand(['git', 'clone', '--depth=1', $url, $tmpdir, '-b', $config_branch], false, 120);
            if (!$clone_result) {
                $local_adapter = $this->getLocalAdapterForTempDir($tmpdir);
            } else {
                $this->log($this->getLastStdOut());
                $this->log($this->getLastStdErr());
                throw new GitCloneException('Problem with git clone of the config branch. Exit code was ' . $clone_result);
            }
            if (!$this->chdir($this->composerJsonDir)) {
                throw new ChdirException('Problem with changing dir to the clone dir of the config branch.');
            }
        }
        $this->composerGetter = new ComposerFileGetter($local_adapter);
        if (!$this->composerGetter->hasComposerFile()) {
            throw new \InvalidArgumentException('No composer.json file found.');
        }
        $composer_json_data = $this->composerGetter->getComposerJsonData();
        if (false == $composer_json_data) {
            throw new \InvalidArgumentException('Invalid composer.json file');
        }
        $config = $this->ensureFreshConfig($composer_json_data);
        $this->runAuthExport($hostname);
        $this->doComposerInstall($config);
        $config = $this->ensureFreshConfig($composer_json_data);
        $this->client = $this->getClient($this->slug);
        $this->privateClient = $this->getClient($this->slug);
        $this->privateClient->authenticate($this->userToken, null);
        if ($is_bitbucket && $bitbucket_user) {
            $this->privateClient->authenticate($bitbucket_user, $this->userToken);
        }

        $this->logger->log('info', new Message('Checking private status of repo', Message::COMMAND));
        $this->isPrivate = $this->checkPrivateStatus();
        $this->logger->log('info', new Message('Checking default branch of repo', Message::COMMAND));
        $default_branch = $this->checkDefaultBranch();

        if ($default_branch) {
            $this->log('Default branch set in project is ' . $default_branch);
        }
        // We also allow the project to override this for violinist.
        if ($config->getDefaultBranch()) {
            // @todo: Would be better to make sure this can actually be set, based on the branches available. Either
            // way, if a person configures this wrong, several parts will fail spectacularly anyway.
            $default_branch = $config->getDefaultBranch();
            $this->log('Default branch overridden by config and set to ' . $default_branch);
        }
        // Now make sure we are actually on that branch.
        if ($this->execCommand(['git', 'remote', 'set-branches', 'origin', "*"])) {
            // We had a problem.
            $this->log($this->getLastStdOut());
            $this->log($this->getLastStdErr());
            throw new \Exception('There was an error trying to configure default branch');
        }
        if ($this->execCommand(['git', 'fetch', 'origin', $default_branch])) {
            // We had a problem.
            $this->log($this->getLastStdOut());
            $this->log($this->getLastStdErr());
            throw new \Exception('There was an error trying to fetch default branch');
        }
        if ($this->execCommand(['git', 'checkout', $default_branch])) {
            // We had a problem.
            $this->log($this->getLastStdOut());
            $this->log($this->getLastStdErr());
            throw new \Exception('There was an error trying to switch to default branch');
        }
        // Re-read the composer.json file, since it can be different on the default branch.
        $this->doComposerInstall($config);
        if (!$uses_config_branch) {
            $composer_json_data = $this->composerGetter->getComposerJsonData();
            $config = $this->ensureFreshConfig($composer_json_data);
        }
        $this->runAuthExport($hostname);
        $this->handleDrupalContribSa($composer_json_data);
        $this->handleTimeIntervalSetting($config);
        $lock_file = $this->composerJsonDir . '/composer.lock';
        $initial_composer_lock_data = false;
        $security_alerts = [];
        if (@file_exists($lock_file)) {
            // We might want to know whats in here.
            $initial_composer_lock_data = json_decode(file_get_contents($lock_file));
        }
        $this->lockFileContents = $initial_composer_lock_data;
        if ($config->shouldAlwaysUpdateAll() && !$initial_composer_lock_data) {
            $this->log('Update all enabled, but no lock file present. This is not supported');
            $this->cleanUp();
            return;
        }
        $this->doComposerInstall($config);
        // Now read the lockfile.
        $composer_lock_after_installing = json_decode(@file_get_contents($this->composerJsonDir . '/composer.lock'));
        // And do a quick security check in there as well.
        try {
            $this->log('Checking for security issues in project.');
            $checker = $this->checkerFactory->getChecker();
            $result = $checker->checkDirectory($this->composerJsonDir);
            // Make sure this is an array now.
            if (!$result) {
                $result = [];
            }
            $this->log('Found ' . count($result) . ' security advisories for packages installed', 'message', [
                'result' => $result,
            ]);
            foreach ($result as $name => $value) {
                $this->log("Security update available for $name");
            }
            if (count($result)) {
                $security_alerts = $result;
            }
        } catch (\Exception $e) {
            $this->log('Caught exception while looking for security updates:');
            $this->log($e->getMessage());
        }
        // We also want to consult the Drupal security advisories, since the FriendsOfPHP/security-advisories
        // repo is a manual job merging and maintaining. On top of that, it requires the built container to be
        // up to date. So here could be several hours of delay on critical stuff.
        $this->attachDrupalAdvisories($security_alerts);
        $direct = null;
        if ($config->shouldCheckDirectOnly()) {
            $this->log('Checking only direct dependencies since config option check_only_direct_dependencies is enabled');
            $direct = '--direct';
        }
        // If we should always update all, then of course we should not only check direct dependencies outdated.
        // Regardless of the option above actually.
        if ($config->shouldAlwaysUpdateAll()) {
            $this->log('Checking all (not only direct dependencies) since config option always_update_all is enabled');
            $direct = null;
        }
        // If we should allow indirect packages to updated via running composer update my/direct, then we need to
        // uncover which indirect are actually out of date. Meaning direct is required to be false.
        if ($config->shouldUpdateIndirectWithDirect()) {
            $this->log('Checking all (not only direct dependencies) since config option allow_update_indirect_with_direct is enabled');
            $direct = null;
        }
        $composer_outdated_command = [
            'composer',
            'outdated',
            '--format=json',
            '--no-interaction',
        ];
        if ($direct) {
            $composer_outdated_command[] = $direct;
        }
        switch ($config->getComposerOutdatedFlag()) {
            case 'patch':
                $composer_outdated_command[] = '--patch-only';
                break;
            default:
                $composer_outdated_command[] = '--minor-only';
                break;
        }
        $this->execCommand($composer_outdated_command);
        $raw_data = $this->getLastStdOut();
        $json_update = @json_decode($raw_data);
        if (!$json_update) {
            // We had a problem.
            $this->log($this->getLastStdOut());
            $this->log($this->getLastStdErr());
            throw new \Exception('The output for available updates could not be parsed as JSON');
        }
        if (!isset($json_update->installed)) {
            // We had a problem.
            $this->log($this->getLastStdOut());
            $this->log($this->getLastStdErr());
            throw new \Exception(
                'JSON output from composer was not looking as expected after checking updates'
            );
        }
        $data = $json_update->installed;
        if (!is_array($data)) {
            $this->log('Update data was in wrong format or missing. This is an error in violinist and should be reported');
            $this->log(print_r($raw_data, true), Message::COMMAND, [
              'data' => $raw_data,
              'data_guessed' => $data,
            ]);
            $this->cleanUp();
            return;
        }
        // Only update the ones in the allow list, if indicated.
        $handler = AllowListHandler::createFromConfig($config);
        // If we have an allow list, we should also make sure to include the
        // direct ones in it, if indicated.
        if ($config->getAllowList() && $config->shouldAlwaysAllowDirect()) {
            $require_list = [];
            if (!empty($composer_json_data->require)) {
                $require_list = array_keys(get_object_vars($composer_json_data->require));
            }
            if (!empty($composer_json_data->{'require-dev'})) {
                $require_list = array_merge($require_list, array_keys(get_object_vars($composer_json_data->{'require-dev'})));
            }
            $handler = AllowListHandler::createFromArray(array_merge($require_list, $config->getAllowList()));
        }
        $handler->setLogger($this->getLogger());
        $data = $handler->applyToItems($data);
        // Remove non-security packages, if indicated.
        if ($config->shouldOnlyUpdateSecurityUpdates()) {
            $this->log('Project indicated that it should only receive security updates. Removing non-security related updates from queue');
            foreach ($data as $delta => $item) {
                try {
                    $package_name_in_composer_json = Helpers::getComposerJsonName($composer_json_data, $item->name, $this->composerJsonDir);
                    if (isset($security_alerts[$package_name_in_composer_json])) {
                        continue;
                    }
                } catch (\Exception $e) {
                    // Totally fine. Let's check if it's there just by the name we have right here.
                    if (isset($security_alerts[$item->name])) {
                        continue;
                    }
                }
                unset($data[$delta]);
                $this->log(sprintf('Skipping update of %s because it is not indicated as a security update', $item->name));
            }
        }
        // Remove block listed packages.
        $block_list = $config->getBlockList();
        if (!is_array($block_list)) {
                $this->log('The format for the package block list was not correct. Expected an array, got ' . gettype($composer_json_data->extra->violinist->blacklist), Message::VIOLINIST_ERROR);
        } else {
            foreach ($data as $delta => $item) {
                if (in_array($item->name, $block_list)) {
                    $this->log(sprintf('Skipping update of %s because it is on the block list', $item->name), Message::BLACKLISTED, [
                        'package' => $item->name,
                    ]);
                    unset($data[$delta]);
                    continue;
                }
                // Also try to match on wildcards.
                foreach ($block_list as $block_list_item) {
                    if (fnmatch($block_list_item, $item->name)) {
                        $this->log(sprintf('Skipping update of %s because it is on the block list by pattern %s', $item->name, $block_list_item), Message::BLACKLISTED, [
                            'package' => $item->name,
                        ]);
                        unset($data[$delta]);
                        continue 2;
                    }
                }
            }
        }
        // Remove dev dependencies, if indicated.
        if (!$config->shouldUpdateDevDependencies()) {
            $this->log('Removing dev dependencies from updates since the option update_dev_dependencies is disabled');
            $filterer = DevDepsOnlyFilterer::create($composer_lock_after_installing, $composer_json_data);
            $data = $filterer->filter($data);
        }
        foreach ($data as $delta => $item) {
            // Also unset those that are in an unexpected format. A new thing seen in the wild has been this:
            // {
            //    "name": "symfony/css-selector",
            //    "version": "v2.8.49",
            //    "description": "Symfony CssSelector Component"
            // }
            // They should ideally include a latest version and latest status.
            if (!isset($item->latest) || !isset($item->{'latest-status'})) {
                unset($data[$delta]);
            } else {
                // If a package is abandoned, we do not really want to know. Since we can't update it anyway.
                if (isset($item->version) && ($item->latest === $item->version || $item->{'latest-status'} === 'up-to-date')) {
                    unset($data[$delta]);
                }
            }
        }
        if (empty($data)) {
            $this->log('No updates found');
            $this->cleanUp();
            return;
        }
        // Try to log what updates are found.
        $this->log('The following updates were found:');
        $updates_string = '';
        foreach ($data as $delta => $item) {
            $updates_string .= sprintf(
                "%s: %s installed, %s available (type %s)\n",
                $item->name,
                $item->version,
                $item->latest,
                $item->{'latest-status'}
            );
        }
        $this->log($updates_string, Message::UPDATE, [
            'packages' => $data,
        ]);
        // Try to see if we have already dealt with this (i.e already have a branch for all the updates.
        $branch_user = $this->forkUser;
        if ($this->isPrivate) {
            $branch_user = $user_name;
        }
        $branch_slug = new Slug();
        $branch_slug->setProvider('github.com');
        $branch_slug->setUserName($branch_user);
        $branch_slug->setUserRepo($user_repo);
        $branches_flattened = [];
        $prs_named = NamedPrs::createFromArray([]);
        $default_base = null;
        try {
            if ($default_base_upstream = $this->privateClient->getDefaultBase($this->slug, $default_branch)) {
                $default_base = $default_base_upstream;
            }
            $prs_named = $this->privateClient->getPrsNamed($this->slug);
            // These can fail if we have not yet created a fork, and the repo is public. That is why we have them at the
            // end of this try/catch, so we can still know the default base for the original repo, and its pull
            // requests.
            if (!$default_base) {
                $default_base = $this->getPrClient()->getDefaultBase($branch_slug, $default_branch);
            }
            $branches_flattened = $this->getPrClient()->getBranchesFlattened($branch_slug);
        } catch (RuntimeException $e) {
            // Safe to ignore.
            $this->log('Had a runtime exception with the fetching of branches and Prs: ' . $e->getMessage());
        }
        if ($default_base && $default_branch) {
            $this->log(sprintf('Current commit SHA for %s is %s', $default_branch, $default_base));
        }
        $is_allowed_out_of_date_pr = [];
        $one_pr_per_dependency = $config->shouldUseOnePullRequestPerPackage();
        foreach ($data as $delta => $item) {
            $branch_name = Helpers::createBranchName($item, $one_pr_per_dependency, $config);
            if (in_array($branch_name, $branches_flattened)) {
                // Is there a PR for this?
                $prs_named_array = $prs_named->getAllPrsNamed();
                if (array_key_exists($branch_name, $prs_named_array)) {
                    $this->countPR($item->name);
                    if (!$default_base && !$one_pr_per_dependency) {
                        $this->log(sprintf('Skipping %s because a pull request already exists', $item->name), Message::PR_EXISTS, [
                            'package' => $item->name,
                        ]);
                        unset($data[$delta]);
                        $this->closeOutdatedPrsForPackage($item->name, $item->version, $config, $prs_named_array[$branch_name]['number'], $prs_named, $default_branch);
                    }
                    // Is the pr up to date?
                    if ($prs_named_array[$branch_name]['base']['sha'] == $default_base) {
                        // Create a fake "post-update-data" object.
                        $fake_post_update = (object) [
                            'version' => $item->latest,
                        ];
                        $security_update = false;
                        $package_name_in_composer_json = $item->name;
                        try {
                            $package_name_in_composer_json = Helpers::getComposerJsonName($composer_json_data, $item->name, $this->composerJsonDir);
                        } catch (\Exception $e) {
                            // If this was a package that we somehow got because we have allowed to update other than direct
                            // dependencies we can avoid re-throwing this.
                            if ($config->shouldCheckDirectOnly()) {
                                throw $e;
                            }
                            // Taking a risk :o.
                            $package_name_in_composer_json = $item->name;
                        }
                        if (isset($security_alerts[$package_name_in_composer_json])) {
                            $security_update = true;
                        }
                        // If the title does not match, it means either has there arrived a security issue for the
                        // update (new title), or we are doing "one-per-dependency", and the title should be something
                        // else with this new update. Either way, we want to continue this. Continue in this context
                        // would mean, we want to keep this for update checking still, and not unset it from the update
                        // array. This will mean it will probably get an updated title later.
                        if ($prs_named_array[$branch_name]['title'] != $this->getPrParamsCreator()->createTitle($item, $fake_post_update, $security_update)) {
                            $this->log(sprintf('Updating the PR of %s since the computed title does not match the title.', $item->name), Message::MESSAGE);
                            continue;
                        }
                        $context = [
                            'package' => $item->name,
                        ];
                        if (!empty($prs_named_array[$branch_name]['html_url'])) {
                            $context['url'] = $prs_named_array[$branch_name]['html_url'];
                        }
                        $this->log(sprintf('Skipping %s because a pull request already exists', $item->name), Message::PR_EXISTS, $context);
                        $this->closeOutdatedPrsForPackage($item->name, $item->version, $config, $prs_named_array[$branch_name]['number'], $prs_named, $default_branch);
                        unset($data[$delta]);
                    } else {
                        $is_allowed_out_of_date_pr[] = $item->name;
                    }
                }
            }
        }
        if ($config->shouldUpdateIndirectWithDirect()) {
            $this->log('Config suggested we should update indirect with direct. Altering the update data based on this');
            $filterer = IndirectWithDirectFilterer::create($composer_lock_after_installing, $composer_json_data);
            $data = $filterer->filter($data);
        }
        if (!count($data)) {
            $this->log('No updates that have not already been pushed.');
            $this->cleanUp();
            return;
        }

        // Unshallow the repo, for syncing it.
        $this->execCommand(['git', 'pull', '--unshallow'], false, 600);
        // If the repo is private, we need to push directly to the repo.
        if (!$this->isPrivate) {
            $this->preparePrClient();
            $this->log('Creating fork to ' . $this->forkUser);
            $this->client->createFork($user_name, $user_repo, $this->forkUser);
        }
        $update_type = self::UPDATE_INDIVIDUAL;
        if ($config->shouldAlwaysUpdateAll()) {
            $update_type = self::UPDATE_ALL;
        }
        $this->log('Config suggested update type ' . $update_type);
        if ($this->project && $this->project->shouldUpdateAll()) {
            // Only log this if this might end up being surprising. I mean override all with all. So what?
            if ($update_type === self::UPDATE_INDIVIDUAL) {
                $this->log('Override of update type from project data. Probably meaning first run, allowed update all');
            }
            $update_type = self::UPDATE_ALL;
        }
        switch ($update_type) {
            case self::UPDATE_INDIVIDUAL:
                $updater = new IndividualUpdater();
                $updater->setLogger($this->logger);
                $updater->setCWD($this->getCwd());
                $updater->setExecuter($this->executer);
                $updater->setPrCounter($this->getPrCounter());
                $updater->setComposerJsonDir($this->composerJsonDir);
                $updater->setMessageFactory($this->messageFactory);
                $updater->setClient($this->getPrClient());
                $updater->setIsPrivate($this->isPrivate);
                $updater->setSlug($this->slug);
                $updater->setAuthentication($this->untouchedUserToken);
                $updater->setAssigneesAllowed($this->assigneesAllowed);
                if ($this->forkUser) {
                    $updater->setForkUser($this->forkUser);
                }
                $updater->setTmpDir($this->tmpDir);
                if ($this->project) {
                    $updater->setProjectData($this->project);
                }
                $updater->handleUpdate($data, $composer_lock_after_installing, $composer_json_data, $one_pr_per_dependency, $initial_composer_lock_data, $prs_named, $default_base, $hostname, $default_branch, $security_alerts, $is_allowed_out_of_date_pr, $config);
                break;

            case self::UPDATE_ALL:
                $this->handleUpdateAll($initial_composer_lock_data, $composer_lock_after_installing, $security_alerts, $config, $default_base, $default_branch, $prs_named);
                break;
        }
        // Clean up.
        $this->cleanUp();
    }

    protected function getPrParamsCreator()
    {
        if (!$this->prParamsCreator instanceof PrParamsCreator) {
            $this->prParamsCreator = new PrParamsCreator($this->messageFactory, $this->project);
        }
        return $this->prParamsCreator;
    }

    protected function ensureFreshConfig(\stdClass $composer_json_data) : Config
    {
        return Config::createFromComposerDataInPath($composer_json_data, sprintf('%s/%s', $this->composerJsonDir, 'composer.json'));
    }

    protected function handleUpdateAll($initial_composer_lock_data, $composer_lock_after_installing, $alerts, Config $config, $default_base, $default_branch, NamedPrs $prs_named)
    {
        // We are going to hack an item here. We want the package to be "all" and the versions to be blank.
        $item = (object) [
            'name' => 'violinist-all',
            'version' => '',
            'latest' => '',
        ];
        $branch_name = Helpers::createBranchName($item, false, $config);
        $pr_params = [];
        $security_update = false;
        try {
            $this->switchBranch($branch_name);
            $status = $this->execCommand(['composer', 'update']);
            if ($status) {
                throw new NotUpdatedException('Composer update command exited with status code ' . $status);
            }
            // Now let's find out what has actually been updated.
            $new_lock_contents = json_decode(file_get_contents($this->composerJsonDir . '/composer.lock'));
            $comparer = new LockDataComparer($composer_lock_after_installing, $new_lock_contents);
            $list = $comparer->getUpdateList();
            if (empty($list)) {
                // That's too bad. Let's throw an exception for this.
                throw new NotUpdatedException('No updates detected after running composer update');
            }
            // Now see if any of the packages updated was in the alerts.
            foreach ($list as $value) {
                if (empty($alerts[$value->getPackageName()])) {
                    continue;
                }
                $security_update = true;
            }
            $this->log('Successfully ran command composer update for all packages');
            $title = 'Update all composer dependencies';
            if ($security_update) {
                // @todo: Use message factory and package.
                $title = sprintf('[SECURITY] %s', $title);
            }
            // We can do this, since the body creates a title, which it does not use. This is only used for the title.
            // Which, again, we do not use.
            $fake_item = $fake_post = (object) [
                'name' => 'all',
                'version' => '0.0.0',
            ];
            $body = $this->getPrParamsCreator()->createBody($fake_item, $fake_post, null, $security_update, $list);
            $pr_params = $this->getPrParamsCreator()->getPrParams($this->forkUser, $this->isPrivate, $this->getSlug(), $branch_name, $body, $title, $default_branch, $config);
            // OK, so... If we already have a branch named the name we are about to use. Is that one a branch
            // containing all the updates we now got? And is it actually up to date with the target branch? Of course,
            // if there is no such branch, then we will happily push it.
            $prs_named_array = $prs_named->getAllPrsNamed();
            if (!empty($prs_named_array[$branch_name])) {
                $up_to_date = false;
                if (!empty($prs_named_array[$branch_name]['base']['sha']) && $prs_named_array[$branch_name]['base']['sha'] == $default_base) {
                    $up_to_date = true;
                }
                $should_update = Helpers::shouldUpdatePr($branch_name, $pr_params, $prs_named);
                if (!$should_update && $up_to_date) {
                    // Well well well. Let's not push this branch over and over, shall we?
                    $this->log(sprintf('The branch %s with all updates is already up to date. Aborting the PR update', $branch_name));
                    return;
                }
            }
            $this->commitFilesForAll($config);
            $this->pushCode($branch_name, $default_base, $initial_composer_lock_data);
            $pullRequest = $this->createPullrequest($pr_params);
            if (!empty($pullRequest['html_url'])) {
                $this->log($pullRequest['html_url'], Message::PR_URL, [
                    'package' => 'all',
                ]);
                $this->handleAutomerge($config, $pullRequest, $security_update);
            }
        } catch (ValidationFailedException $e) {
            // @todo: Do some better checking. Could be several things, this.
            $this->handlePossibleUpdatePrScenario($e, $branch_name, $pr_params, $prs_named, $config, $security_update);
        } catch (\Gitlab\Exception\RuntimeException $e) {
            $this->handlePossibleUpdatePrScenario($e, $branch_name, $pr_params, $prs_named, $config, $security_update);
        } catch (NotUpdatedException $e) {
            $this->log($this->getLastStdOut());
            $this->log($this->getLastStdErr());
            $not_updated_context = [
                'package' => sprintf('all:%s', $default_base),
            ];
            $this->log("Could not update all dependencies with composer update", Message::NOT_UPDATED, $not_updated_context);
        } catch (\Throwable $e) {
            $this->log('Caught exception while running update all: ' . $e->getMessage());
        }
    }

    protected function commitFilesForAll(Config $config)
    {
        $this->cleanRepoForCommit();
        $creator = $this->getCommitCreator($config);
        $msg = $creator->generateMessageFromString('Update all dependencies');
        $this->commitFiles($msg);
    }

    protected function handlePossibleUpdatePrScenario(\Exception $e, $branch_name, $pr_params, NamedPrs $prs_named, Config $config, $security_update = false)
    {
        $prs_named_array = $prs_named->getAllPrsNamed();
        $this->log('Had a problem with creating the pull request: ' . $e->getMessage(), 'error');
        if (Helpers::shouldUpdatePr($branch_name, $pr_params, $prs_named)) {
            $this->log('Will try to update the PR based on settings.');
            $this->getPrClient()->updatePullRequest($this->slug, $prs_named_array[$branch_name]['number'], $pr_params);
        }
        if (!empty($prs_named_array[$branch_name])) {
            $this->handleAutoMerge($config, $prs_named_array[$branch_name], $security_update);
            $this->handleLabels($config, $prs_named_array[$branch_name], $security_update);
        }
    }

    protected function handleLabels(Config $config, $pullRequest, $security_update = false) : void
    {
        $labels_allowed = false;
        $labels_allowed_roles = [
            'agency',
            'enterprise',
        ];
        if ($this->project && $this->project->getRoles()) {
            foreach ($this->project->getRoles() as $role) {
                if (in_array($role, $labels_allowed_roles)) {
                    $labels_allowed = true;
                }
            }
        }
        if (!$labels_allowed) {
            return;
        }
        Helpers::handleLabels($this->getPrClient(), $this->getLogger(), $this->slug, $config, $pullRequest, $security_update);
    }

    protected function handleAutoMerge(Config $config, $pullRequest, $security_update = false) : void
    {
        Helpers::handleAutoMerge($this->getPrClient(), $this->getLogger(), $this->slug, $config, $pullRequest, $security_update);
    }

    /**
     * Get the messages that are logged.
     *
     * @return \eiriksm\CosyComposer\Message[]
     *   The logged messages.
     */
    public function getOutput()
    {
        $msgs = [];
        if (!$this->logger instanceof ArrayLogger) {
            return $msgs;
        }
        /** @var ArrayLogger $my_logger */
        $my_logger = $this->logger;
        foreach ($my_logger->get() as $message) {
            $msg = $message['message'];
            if (!$msg instanceof Message && is_string($msg)) {
                $msg = new Message($msg);
            }
            $msg->setContext($message['context']);
            if (isset($message['context']['command'])) {
                $msg = new Message($msg->getMessage(), Message::COMMAND);
                $msg->setContext($message['context']);
            }
            $msgs[] = $msg;
        }
        return $msgs;
    }

    /**
     * Cleans up after the run.
     */
    private function cleanUp()
    {
        // Run composer install again, so we can get rid of newly installed updates for next run.
        $this->execCommand(['composer', 'install', '--no-ansi', '-n'], false, 1200);
        $this->chdir('/tmp');
        $this->log('Cleaning up after update check.');
        $this->execCommand(['rm', '-rf', $this->tmpDir], false, 300);
        if (file_exists('/usr/local/bin/composer.bak')) {
            rename('/usr/local/bin/composer.bak', '/usr/local/bin/composer');
        }
    }

    /**
     * Executes a command.
     */
    protected function execCommand(array $command, $log = true, $timeout = 120, $env = [])
    {
        $this->executer->setCwd($this->getCwd());
        return $this->executer->executeCommand($command, $log, $timeout, $env);
    }

    /**
     * Log a message.
     *
     * @param string $message
     */
    protected function log($message, $type = 'message', $context = [])
    {

        $this->getLogger()->log('info', new Message($message, $type), $context);
    }

    protected function attachDrupalAdvisories(array &$alerts)
    {
        // Also though. If the only alert we have is for the package with
        // literally "drupal/core" we need to make sure it's attached to the
        // other names as well.
        $known_names = [
            'drupal/core-recommended',
            'drupal/core-composer-scaffold',
            'drupal/core-project-message',
            'drupal/core',
            'drupal/drupal',
        ];
        if (!empty($alerts['drupal/core'])) {
            foreach ($known_names as $known_name) {
                if (!empty($alerts[$known_name])) {
                    continue;
                }
                $alerts[$known_name] = $alerts['drupal/core'];
            }
        }
        if (!$this->lockFileContents) {
            return;
        }
        $data = ComposerLockData::createFromString(json_encode($this->lockFileContents));
        try {
            $drupal = $data->getPackageData('drupal/core');
            // Now see if a newer version is available, and if it is a security update.
            $endpoint = 'current';
            $version_parts = explode('.', $drupal->version);
            $major_version = $version_parts[0];
            // Only 7.x and 8.x use their own endpoint,
            if (in_array($major_version, ['7', '8'])) {
                $endpoint = $major_version . '.x';
            }
            if ((int) $major_version < 7) {
                throw new \Exception(sprintf('Drupal version %s is too old to check for security updates using drupal.org endpoint', $major_version));
            }
            $client = $this->getHttpClient();
            $url = sprintf('https://updates.drupal.org/release-history/drupal/%s', $endpoint);
            $request = new Request('GET', $url);
            $response = $client->sendRequest($request);
            $data = $response->getBody()->getContents();
            $xml = @simplexml_load_string($data);
            if (!$xml) {
                return;
            }
            if (empty($xml->releases->release)) {
                return;
            }
            $drupal_version_array = explode('.', $drupal->version);
            $active_branch = sprintf('%s.%s', $drupal_version_array[0], $drupal_version_array[1]);
            $supported_branches = explode(',', (string) $xml->supported_branches);
            $is_supported = false;
            foreach ($supported_branches as $branch) {
                if (strpos($branch, $active_branch) === 0) {
                    $is_supported = true;
                }
            }
            foreach ($xml->releases->release as $release) {
                if (empty($release->version)) {
                    continue;
                }
                if (empty($release->terms) || empty($release->terms->term)) {
                    continue;
                }
                $version = (string) $release->version;
                // If they are not on the same branch, then let's skip it as well.
                if ($endpoint !== '7.x') {
                    if ($is_supported && strpos($version, $active_branch) !== 0) {
                        continue;
                    }
                }
                if (version_compare($version, $drupal->version) !== 1) {
                    continue;
                }
                $is_sec = false;
                foreach ($release->terms->term as $item) {
                    $type = (string) $item->value;
                    if ($type === 'Security update') {
                        $is_sec = true;
                    }
                }
                if (!$is_sec) {
                    continue;
                }
                if (strpos($release->version, $major_version) !== 0) {
                    // You know what. We must be checking version 10.x against
                    // version 9.x. Not ideal, is it? Makes for some false
                    // positives (or rather negatives, I guess).
                    continue;
                }
                $this->log('Found a security update in the update XML. Will populate advisories from this, if not already set.');
                foreach ($known_names as $known_name) {
                    if (!empty($alerts[$known_name])) {
                        continue;
                    }
                    $alerts[$known_name] = [
                        'version' => $version,
                    ];
                }
                break;
            }
        } catch (\Throwable $e) {
            // Totally fine.
        }
    }

   /**
    * Changes to a different directory.
    */
    private function chdir($dir)
    {
        if (!file_exists($dir)) {
            return false;
        }
        $this->setCWD($dir);
        return true;
    }

    protected function setCWD($dir)
    {
        $this->cwd = $dir;
    }


    /**
     * @return string
     */
    public function getTmpDir()
    {
        return $this->tmpDir;
    }

    /**
     * @param Slug $slug
     *
     * @return ProviderInterface
     */
    private function getClient(Slug $slug)
    {
        if (!$this->providerFactory instanceof ProviderFactory) {
            $this->setProviderFactory(new ProviderFactory());
        }
        return $this->providerFactory->createFromHost($slug, $this->urlArray);
    }

    /**
     * Get the client we should use for the PRs we create.
     */
    private function getPrClient() : ProviderInterface
    {
        if ($this->isPrivate) {
            return $this->privateClient;
        }
        $this->preparePrClient();
        $this->client->authenticate($this->userToken, null);
        return $this->client;
    }

    private function preparePrClient() : void
    {
        // We are only allowed to use the public github wrapper if the magic env
        // for this is set, which it will be in jobs coming from the SaaS
        // offering, but not for self hosted.
        $this->logger->log('info', new Message('Checking if we should enable the public github wrapper', Message::COMMAND));
        if (!self::shouldEnablePublicGithubWrapper()) {
            // The client should hopefully be fully prepared.
            $this->logger->log('info', new Message('Public github wrapper not enabled', Message::COMMAND));
            return;
        }
        if (!$this->isPrivate) {
            $this->logger->log('info', new Message('Public github wrapper enabled', Message::COMMAND));
            if (!$this->client instanceof PublicGithubWrapper) {
                $this->client = new PublicGithubWrapper(new Client());
            }
            $this->client->setUserToken($this->userToken);
            $this->client->setUrlFromTokenUrl($this->tokenUrl);
            $this->client->setProject($this->project);
        }
    }

    private function checkDefaultBranch() : ?string
    {
        $default_branch = null;
        try {
            $default_branch = $this->privateClient->getDefaultBranch($this->slug);
        } catch (\Throwable $e) {
            // Could be a personal access token.
            if (!method_exists($this->privateClient, 'authenticatePersonalAccessToken')) {
                throw $e;
            }
            try {
                $this->privateClient->authenticatePersonalAccessToken($this->userToken, null);
                $default_branch = $this->privateClient->getDefaultBranch($this->slug);
            } catch (\Throwable $other_exception) {
                throw $e;
            }
        }
        return $default_branch;
    }

    private function checkPrivateStatus() : bool
    {
        if (!self::shouldEnablePublicGithubWrapper()) {
            return true;
        }
        try {
            return $this->privateClient->repoIsPrivate($this->slug);
        } catch (\Throwable $e) {
            // Could be a personal access token.
            if (!method_exists($this->privateClient, 'authenticatePersonalAccessToken')) {
                return true;
            }
            try {
                $this->privateClient->authenticatePersonalAccessToken($this->userToken, null);
                return $this->privateClient->repoIsPrivate($this->slug);
            } catch (\Throwable $other_exception) {
                throw $e;
            }
        }
    }

    protected function getlockFileContents()
    {
        return $this->lockFileContents;
    }

    public static function shouldEnablePublicGithubWrapper() : bool
    {
        return !empty(getenv('USE_GITHUB_PUBLIC_WRAPPER'));
    }
}
