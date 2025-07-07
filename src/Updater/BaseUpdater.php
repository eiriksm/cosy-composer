<?php

namespace eiriksm\CosyComposer\Updater;

use eiriksm\CosyComposer\AssigneesAllowedTrait;
use eiriksm\CosyComposer\ComposerInstallTrait;
use eiriksm\CosyComposer\GitCommandsTrait;
use eiriksm\CosyComposer\Helpers;
use eiriksm\CosyComposer\Message;
use eiriksm\CosyComposer\PrCounterTrait;
use eiriksm\CosyComposer\ProcessFactoryWrapper;
use eiriksm\CosyComposer\ProviderInterface;
use eiriksm\CosyComposer\Providers\NamedPrs;
use eiriksm\CosyComposer\SlugAwareTrait;
use eiriksm\CosyComposer\TemporaryDirectoryAwareTrait;
use eiriksm\CosyComposer\TokenAwareTrait;
use eiriksm\CosyComposer\TokenChooser;
use eiriksm\ViolinistMessages\ViolinistMessages;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Violinist\ChangelogFetcher\ChangelogRetriever;
use Violinist\ChangelogFetcher\DependencyRepoRetriever;
use Violinist\ComposerLockData\ComposerLockData;
use Violinist\Config\Config;
use Violinist\GitLogFormat\ChangeLogData;
use Violinist\ProjectData\ProjectData;
use Wa72\SimpleLogger\ArrayLogger;
use function peterpostmann\uri\parse_uri;

abstract class BaseUpdater implements UpdaterInterface
{
    use LoggerAwareTrait;
    use ComposerInstallTrait;
    use PrCounterTrait;
    use GitCommandsTrait;
    use SlugAwareTrait;
    use TokenAwareTrait;
    use AssigneesAllowedTrait;
    use TemporaryDirectoryAwareTrait;

    /**
     * @var bool
     */
    protected $isPrivate = false;

    /**
     * @var \eiriksm\CosyComposer\CommandExecuter
     */
    protected $executer;

    /**
     * @var string
     */
    protected $cwd;

    /**
     * @var string
     */
    protected $initialComposerLockData;

    /**
     * @var \eiriksm\ViolinistMessages\ViolinistMessages
     */
    protected $messageFactory;

    /**
     * @var ProviderInterface
     */
    protected $client;

    /**
     * @var ProjectData
     */
    protected $projectData;

    /**
     * @var ChangelogRetriever
     */
    protected $fetcher;

    /**
     * @var string
     */
    protected $forkUser;

    public function setForkUser(string $user)
    {
        $this->forkUser = $user;
    }

    public function setIsPrivate($is_private)
    {
        $this->isPrivate = $is_private;
    }

    protected function preparePrClient()
    {
        // No-op, we just want it compatible with the interface.
    }

    protected function getPrClient() : ProviderInterface
    {
        return $this->client;
    }

    public function setClient(ProviderInterface $client)
    {
        $this->client = $client;
    }

    public function setProjectData(ProjectData $projectData)
    {
        $this->projectData = $projectData;
    }

    public function setMessageFactory(ViolinistMessages $messageFactory)
    {
        $this->messageFactory = $messageFactory;
    }

    protected function getlockFileContents()
    {
        return $this->initialComposerLockData;
    }

    protected function log($message, $type = 'message', $context = [])
    {
        $this->getLogger()->log('info', new Message($message, $type), $context);
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
     * Executes a command.
     */
    protected function execCommand(array $command, $log = true, $timeout = 120, $env = [])
    {
        $this->executer->setCwd($this->getCwd());
        return $this->executer->executeCommand($command, $log, $timeout, $env);
    }

    public function setCWD($dir)
    {
        $this->cwd = $dir;
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
     * Helper to retrieve changelog.
     */
    public function retrieveChangeLog($package_name, $lockdata, $version_from, $version_to)
    {
        $lock_data_obj = new ComposerLockData();
        $lock_data_obj->setData($lockdata);
        $data = $lock_data_obj->getPackageData($package_name);
        if (empty($data->source->url)) {
            throw new \Exception('Unknown source or non-git source found for vendor/package. Aborting.');
        }
        $fetcher = $this->getFetcherForUrl($data->source->url);
        $log_obj = $fetcher->retrieveChangelog($package_name, $lockdata, $version_from, $version_to);
        $changelog_string = '';
        $json = json_decode($log_obj->getAsJson());
        foreach ($json as $item) {
            $changelog_string .= sprintf("%s %s\n", $item->hash, $item->message);
        }
        if (mb_strlen($changelog_string) > 60000) {
            // Truncate it to 60K.
            $changelog_string = mb_substr($changelog_string, 0, 60000);
            // Then split it into lines.
            $lines = explode("\n", $changelog_string);
            // Cut off the last one, since it could be partial.
            array_pop($lines);
            // Then append a line saying the changelog was too long.
            $lines[] = sprintf('%s ...more commits found, but message is too long for PR', $version_to);
            $changelog_string = implode("\n", $lines);
        }
        $log = ChangeLogData::createFromString($changelog_string);
        $git_url = preg_replace('/.git$/', '', $data->source->url);
        $repo_parsed = parse_uri($git_url);
        if (!empty($repo_parsed)) {
            switch ($repo_parsed['_protocol']) {
                case 'git@github.com':
                    $git_url = sprintf('https://github.com/%s', $repo_parsed['path']);
                    break;
            }
        }
        $log->setGitSource($git_url);
        return $log;
    }

    protected function retrieveChangedFiles($package_name, $lockdata, $version_from, $version_to)
    {
        $lock_data_obj = new ComposerLockData();
        $lock_data_obj->setData($lockdata);
        $data = $lock_data_obj->getPackageData($package_name);
        if (empty($data) || empty($data->source->url)) {
            throw new \Exception('Unknown source or non-git source found for vendor/package. Aborting.');
        }
        return $this->getFetcherForUrl($data->source->url)
            ->retrieveChangedFiles($package_name, $lockdata, $version_from, $version_to);
    }

    /**
     * @param $lockdata
     * @param $package_name
     * @param $pre_update_data
     * @param $post_update_data
     * @return array
     * @throws \Exception
     */
    public function getReleaseLinks($lockdata, $package_name, $pre_update_data, $post_update_data) : array
    {
        $extra_info = '';
        if (empty($pre_update_data->source->reference) || empty($post_update_data->source->reference)) {
            throw new \Exception('No SHAs to use to compare and retrieve tags for release links');
        }
        if (empty($post_update_data->source->url)) {
            throw new \Exception('No source URL to attempt to parse in post update data source');
        }
        $data = $this->getFetcherForUrl($post_update_data->source->url)->retrieveTagsBetweenShas($lockdata, $package_name, $pre_update_data->source->reference, $post_update_data->source->reference);
        $url = $post_update_data->source->url;
        $url = preg_replace('/.git$/', '', $url);
        $url_parsed = parse_url($url);
        if (empty($url_parsed['host'])) {
            throw new \Exception('No URL to parse in post update data source');
        }
        $link_pattern = null;
        $links = [];
        switch ($url_parsed['host']) {
            case 'github.com':
                $link_pattern = "$url/releases/tag/%s";
                break;

            case 'git.drupalcode.org':
            case 'git.drupal.org':
                $project_name = str_replace('/project/', '', $url_parsed['path']);
                $link_pattern = "https://www.drupal.org/project/$project_name/releases/%s";
                break;

            default:
                throw new \Exception('Git URL host not supported.');
        }
        foreach ($data as $item) {
            $link = sprintf($link_pattern, $item);
            $links[] = sprintf('- [Release notes for tag %s](%s)', $item, $link);
        }
        return $links;
    }

    protected function getFetcherForUrl(string $url) : ChangelogRetriever
    {
        $token_chooser = new TokenChooser($this->slug->getUrl());
        $token_chooser->setUserToken($this->untouchedUserToken);
        $token_chooser->addTokens($this->tokens);
        $fetcher = $this->getFetcher();
        $fetcher->getRetriever()->setAuthToken($token_chooser->getChosenToken($url));
        return $fetcher;
    }

    protected function getFetcher() : ChangelogRetriever
    {
        if (!$this->fetcher instanceof ChangelogRetriever) {
            $cosy_factory_wrapper = new ProcessFactoryWrapper();
            $cosy_factory_wrapper->setExecutor($this->executer);
            $retriever = new DependencyRepoRetriever($cosy_factory_wrapper);
            $this->fetcher = new ChangelogRetriever($retriever, $cosy_factory_wrapper);
        }
        return $this->fetcher;
    }

    protected function closeOutdatedPrsForPackage($package_name, $current_version, Config $config, $pr_id, NamedPrs $prs_named, $default_branch)
    {
        $fake_item = (object) [
            'name' => $package_name,
            'version' => $current_version,
            'latest' => '',
        ];
        $branch_name_prefix = Helpers::createBranchName($fake_item, false, $config);
        $relevant_prs = $prs_named->getPrsFromPackage($package_name);
        if (empty($relevant_prs)) {
            $this->getLogger()->info(new Message('No direct relevant PRs found for package ' . $package_name));
            $relevant_prs = $prs_named->getAllPrsNamed();
        }
        foreach ($relevant_prs as $branch_name => $pr) {
            if (!empty($pr["base"]["ref"])) {
                // The base ref should be what we are actually using for merge requests.
                if ($pr["base"]["ref"] != $default_branch) {
                    continue;
                }
            }
            if ($pr["number"] == $pr_id) {
                // We really don't want to close the one we are considering as the latest one, do we?
                continue;
            }
            // We are just going to assume, if the number of the PR does not match. And the branch name does
            // indeed "match", well. Match as in it updates the exact package from the exact same version. Then
            // the current/recent PR will update to a newer version. Or it could also be that the branch was
            // created while the project was using one PR per version, and then they switched. Either way. These
            // two scenarios are both scenarios we want to handle in such a way that we are closing this PR that
            // is matching.
            if (strpos($branch_name, $branch_name_prefix) === false) {
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
}
