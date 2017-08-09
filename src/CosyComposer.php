<?php

namespace eiriksm\CosyComposer;

use Composer\Command\OutdatedCommand;
use Composer\Console\Application;
use eiriksm\CosyComposer\Exceptions\ChdirException;
use eiriksm\CosyComposer\Exceptions\ComposerInstallException;
use eiriksm\CosyComposer\Exceptions\GitCloneException;
use eiriksm\CosyComposer\Exceptions\GitPushException;
use eiriksm\GitLogFormat\ChangeLogData;
use eiriksm\ViolinistMessages\ViolinistMessages;
use eiriksm\ViolinistMessages\ViolinistUpdate;
use Github\Client;
use Github\Exception\RuntimeException;
use Github\Exception\ValidationFailedException;
use Github\HttpClient\Builder;
use Symfony\Component\Console\Input\ArrayInput;
use Composer\Command\ShowCommand;
use Symfony\Component\Console\Input\InputOption;

class CosyComposer {

  /**
   * @var array
   */
  private $messages = [];

  /**
   * @var bool
   */
  private $verbose = FALSE;

  /**
   * @var string
   */
  protected $tmpDir;

  /**
   * @var Application
   */
  private $app;

  /**
   * @var string
   */
  private $token;

  /**
   * @var string
   */
  private $slug;

  /**
   * @var string
   */
  private $githubUser;

  /**
   * @var string
   */
  private $githubPass;

  /**
   * @var string
   */
  private $forkUser;

  /**
   * @var mixed
   */
  private $chdirCommand = 'chdir';

  /**
   * @var mixed
   */
  private $proc_open = 'proc_open';

  private $proc_close = 'proc_close';

  private $pipes = [];

  private $contentGetter = 'stream_get_contents';

  private $githubUserName;
  private $githubUserPass;
  private $githubEmail;
  private $messageFactory;

  /**
   * @var string
   */
  private $lastStdErr = '';

  /**
   * @var string
   */
  private $lastStdOut = '';

  /**
   * @return string
   */
  public function getLastStdErr() {
    return $this->lastStdErr;
  }

  /**
   * @param string $lastStdErr
   */
  public function setLastStdErr($lastStdErr) {
    $this->lastStdErr = $lastStdErr;
  }

  /**
   * @return string
   */
  public function getLastStdOut() {
    return $this->lastStdOut;
  }

  /**
   * @param string $lastStdOut
   */
  public function setLastStdOut($lastStdOut) {
    $this->lastStdOut = $lastStdOut;
  }

  /**
   * CosyComposer constructor.
   * @param string $token
   * @param string $slug
   */
  public function __construct($token, $slug) {
    $this->token = $token;
    $this->slug = $slug;
    $tmpdir = uniqid();
    $this->tmpDir = sprintf('/tmp/%s', $tmpdir);
    $this->messageFactory = new ViolinistMessages();
  }

  public function setVerbose($verbose) {
    $this->verbose = $verbose;
  }

  public function setGithubAuth($user, $pass) {
    $this->githubUser = $user;
    $this->forkUser = $user;
    $this->githubPass = $pass;
  }

  public function setGithubForkAuth($user, $pass, $mail) {
    $this->githubUserName = $user;
    $this->githubUserPass = $pass;
    $this->githubEmail = $mail;
  }

  /**
   * Set a user to fork to.
   *
   * @param string $user
   */
  public function setForkUser($user) {
    $this->forkUser = $user;
  }

  /**
   * @throws \eiriksm\CosyComposer\Exceptions\ChdirException
   * @throws \eiriksm\CosyComposer\Exceptions\GitCloneException
   */
  public function run() {
    $this->log(sprintf('Starting update check for %s', $this->slug));
    $repo = $this->slug;
    $repo_parts = explode('/', $repo);
    $user_name = $repo_parts[0];
    $user_repo = $repo_parts[1];
    // First set working dir to /tmp (since we might be in the directory of the
    // last processed item, which may be deleted.
    if (!$this->chdir('/tmp')) {
      throw new ChdirException('Problem with changing dir to /tmp');
    }
    $url = sprintf('https://%s:%s@github.com/%s', $this->githubUser, $this->githubPass, $repo);
    $this->log('Cloning repository');
    $clone_result = $this->execCommand('git clone --depth=1 ' . $url . ' ' . $this->tmpDir, FALSE);
    if ($clone_result) {
      // We had a problem.
      throw new GitCloneException('Problem with the execCommand git clone. Exit code was ' . $clone_result);
    }
    $this->log('Repository cloned');
    if (!$this->chdir($this->tmpDir)) {
      throw new ChdirException('Problem with changing dir to the clone dir.');
    }
    $composer_file = $this->tmpDir . '/composer.json';
    if (!file_exists($composer_file)) {
      throw new \InvalidArgumentException('No composer.json file found.');
    }
    if (!$cdata = json_decode(file_get_contents($composer_file))) {
      throw new \InvalidArgumentException('Invalid composer.json file');
    }
    $outdated = new OutdatedCommand();
    $show = new ShowCommand();
    $this->app = new Application();
    $app = $this->app;
    $d = $app->getDefinition();
    $opts = $d->getOptions();
    $opts['no-ansi'] = new InputOption('no-ansi', NULL, 4, TRUE, 'Disable ANSI output');
    $d->setOptions($opts);
    $app->setDefinition($d);
    $app->setAutoExit(FALSE);
    $app->add($outdated);
    $app->add($show);
    $this->doComposerInstall();
    $outdated->setApplication($app);
    $show->setApplication($app);
    $def = $outdated->getDefinition();
    $i = new ArrayInput([
      '--direct' => TRUE,
      '--minor-only' => TRUE,
    ], $def);
    $b = new ArrayOutput();
    $outdated->run($i, $b);
    $data = $b->fetch();
    foreach ($data as $delta => $item) {
      // @todo: Fix this properly.
      if (strpos($item[0], '<warning>') === 0) {
        unset($data[$delta]);
        continue;
      }
    }
    if (empty($data)) {
      $this->cleanup();
      return;
    }
    $client = new Client(new Builder(), 'polaris-preview');
    $client->authenticate($this->token, NULL, Client::AUTH_URL_TOKEN);
    // Get the default branch of the repo.
    $private_client = new Client();
    $private_client->authenticate($this->githubUser, NULL, Client::AUTH_HTTP_TOKEN);
    $repo = $private_client->api('repo')->show($user_name, $user_repo);
    $private = FALSE;
    if ($repo['private']) {
      $private = TRUE;
    }
    $default_branch = $repo['default_branch'];
    // Try to see if we have already dealt with this (i.e already have a branch
    // for all the updates.
    $pr_client = $client;
    $branch_user = $this->forkUser;
    if ($private) {
      $pr_client = $private_client;
      $branch_user = $user_name;
    }
    $branches_flattened = [];
    $prs_named = [];
    $default_base = NULL;
    try {
      $branches = $pr_client->api('repo')->branches($branch_user, $user_repo);
      $branches_upstream = $private_client->api('repo')->branches($user_name, $user_repo);
      $prs = $private_client->api('pr')->all($user_name, $user_repo);

      foreach ($branches as $branch) {
        $branches_flattened[] = $branch['name'];
        if ($branch['name'] == $default_branch) {
          $default_base = $branch['commit']['sha'];
        }
      }

      foreach ($branches_upstream as $branch) {
        if ($branch['name'] == $default_branch) {
          $default_base = $branch['commit']['sha'];
        }
      }

      foreach ($prs as $pr) {
        $prs_named[$pr['head']['ref']] = $pr;
      }
    }
    catch (RuntimeException $e) {
      // Safe to ignore.
    }
    foreach ($data as $delta => $item) {
      // @todo: Fix this properly.
      if (strpos($item[0], '<warning>') === 0) {
        unset($data[$delta]);
        continue;
      }
      // @todo: Fix this properly.
      $item[2] = str_replace('<highlight>!', '', $item[2]);
      $item[2] = str_replace('</highlight>', '', $item[2]);
      $item[2] = trim($item[2]);
      $branch_name = $this->createBranchName($item);
      if (in_array($branch_name, $branches_flattened)) {
        // Is there a PR for this?
        if (array_key_exists($branch_name, $prs_named)) {
          if (!$default_base) {
            unset($data[$delta]);
          }
          // Is the pr up to date?
          if ($prs_named[$branch_name]['base']['sha'] == $default_base) {
            unset($data[$delta]);
          }
        }
      }
    }
    if (empty($data)) {
      $this->log('No updates that have not already been pushed.');
      $this->cleanup();
      return;
    }

    // Unshallow the repo, for syncing it.
    $this->execCommand('git pull --unshallow');
    // If the repo is private, we need to push directly to the repo.
    if (!$private) {
      $fork = $client->api('repo')->forks()->create($user_name, $user_repo, [
        'organization' => $this->forkUser,
      ]);
      $fork_url = sprintf('https://%s:%s@github.com/%s/%s', $this->githubUserName, $this->githubUserPass, $this->forkUser, $user_repo);
      $this->execCommand('git remote add fork ' . $fork_url, FALSE);
      // Sync the fork.
      $this->execCommand('git push fork ' . $default_branch);
    }
    // Now read the lockfile.
    $lockdata = json_decode(file_get_contents($this->tmpDir . '/composer.lock'));
    foreach ($data as $item) {
      // @todo: Fix this properly.
      if (strpos($item[0], '<warning>') === 0) {
        continue;
      }
      try {
        // @todo: Fix this properly.
        $item[2] = str_replace('<highlight>!', '', $item[2]);
        $item[2] = str_replace('</highlight>', '', $item[2]);
        $item[2] = trim($item[2]);
        $package_name = $item[0];
        $version_from = $item[1];
        $version_to = $item[2];
        // See where this package is.
        $req_command = 'require';
        if (!empty($cdata->{'require-dev'}->{$package_name})) {
          $req_command = 'require --dev';
          $req_item = $cdata->{'require-dev'}->{$package_name};
        }
        else {
          $req_item = $cdata->{'require'}->{$package_name};
        }
        // Create a new branch.
        $branch_name = $this->createBranchName($item);
        $this->execCommand('git checkout -b ' . $branch_name);
        // Try to use the same version constraint.
        $version = (string) $req_item;
        switch ($version[0]) {
          case '^':
            $constraint = '^';
            break;

          case '~':
            $constraint = '~';
            break;

          default:
            $constraint = '';
            break;
        }
        $command = sprintf('composer %s %s:%s%s', $req_command, $package_name, $constraint, $version_to);
        $this->execCommand($command);
        $command = 'composer update --with-dependencies ' . $package_name;
        $this->execCommand($command);
        // Clean away the lock file if we are not supposed to use it.
        $this->execCommand('git clean -f composer.*');
        $command = sprintf('GIT_AUTHOR_NAME="%s" GIT_AUTHOR_EMAIL="%s" GIT_COMMITTER_NAME="%s" GIT_COMMITTER_EMAIL="%s" git commit composer.* -m "Update %s"',
          $this->githubUserName,
          $this->githubEmail,
          $this->githubUserName,
          $this->githubEmail,
          $package_name
        );
        if ($this->execCommand($command, FALSE)) {
          throw new \Exception('Error committing the composer files. They are probably not changed.');
        }
        $origin = 'fork';
        if ($private) {
          $origin = 'origin';
        }
        if ($this->execCommand("git push $origin $branch_name --force")) {
          throw new GitPushException('Could not push to ' . $branch_name);
        }
        $this->log('Trying to retrieve changelog for ' . $package_name);
        $changelog = NULL;
        try {
          $changelog = $this->retrieveChangeLog($package_name, $lockdata, $version_from, $version_to);
        }
        catch (\Exception $e) {
          // New feature. Just log it.
          $this->log('Exception for changelog: ' . $e->getMessage());
        }
        $this->log('Creating pull request from ' . $branch_name);
        $head = $this->forkUser . ':' . $branch_name;
        if ($private) {
          $head = $branch_name;
        }
        $body = $this->createBody($item, $changelog);
        $pullRequest = $pr_client->api('pull_request')->create($user_name, $user_repo, array(
          'base'  => $default_branch,
          'head'  => $head,
          'title' => $this->createTitle($item),
          'body'  => $body,
        ));
      }
      catch (ValidationFailedException $e) {
        // @todo: Do some better checking. Could be several things, this.
        $this->log('Had a problem with creating the pull request: ' . $e->getMessage());
      }
      catch (\Exception $e) {
        // @todo: Should probably handle this in some way.
        $this->log('Caught an exception: ' . $e->getMessage());
      }
      $this->log('Checking out default branch - ' . $default_branch);
      $this->execCommand('git checkout ' . $default_branch, FALSE);
    }
    // Clean up.
    $this->cleanUp();
  }

  /**
   * Get the messages that are logged.
   *
   * @return array
   *   The logged messages.
   */
  public function getOutput() {
    return $this->messages;
  }

  /**
   * Cleans up after the run.
   */
  private function cleanUp() {
    $this->log('Cleaning up after update check.');
    $this->log('Storing custom composer cache for later');
    $this->execCommand(sprintf('rsync -az --exclude "composer.*" %s/* %s', $this->tmpDir, $this->createCacheDir()));
    $this->execCommand('rm -rf ' . $this->tmpDir, FALSE);
  }

  /**
   * Returns the cache directory, and creates it if necessary.
   *
   * @return string
   */
  public function createCacheDir() {
    $dir_name = md5($this->slug);
    $path = '/tmp/' . $dir_name;
    if (!file_exists($path)) {
      mkdir($path);
    }
    return $path;
  }

  /**
   * Creates a title for a PR.
   *
   * @param $item
   *   The item in question.
   *
   * @return string
   *   A string ready to use.
   */
  protected function createTitle($item) {
    return $this->messageFactory->getPullRequestTitleLegacy($item);
  }

  /**
   * @param $item
   *
   * @return string
   */
  public function createBody($item, $changelog = NULL) {
    $update = ViolinistUpdate::fromLegacyFormat($item);
    if ($changelog) {
      /** @var \eiriksm\GitLogFormat\ChangeLogData $changelog */
      $update->setChangelog($changelog->getAsMarkdown());
    }
    return $this->messageFactory->getPullRequestBody($update);
  }

  /**
   * @param $item
   *
   * @return mixed
   */
  protected function createBranchName($item) {
    $this->debug('Creating branch name based on ' . print_r($item, TRUE));
    $item_string = sprintf('%s%s%s', $item[0], $item[1], $item[2]);
    // @todo: Fix this properly.
    $result = preg_replace('/[^a-zA-Z0-9]+/', '', $item_string);
    $this->debug('Creating branch named ' . $result);
    return $result;
  }

  protected function execCommand($command, $log = TRUE) {
    if ($log) {
      $this->log("Creating command $command");
    }
    $descriptor_spec = [
      0 => ['pipe', 'r'],
      1 => ['pipe', 'w'],
      2 => ['pipe', 'w'],
    ];
    $pipes = $this->getPipes();
    $func = $this->proc_open;
    $process = $func($command, $descriptor_spec, $pipes, getcwd(), NULL);
    $stdout = $this->getContents($pipes[1]);
    $this->setLastStdOut($stdout);
    $stderr = $this->getContents($pipes[2]);
    $this->setLastStdErr($stderr);
    if (!empty($stdout) && $log) {
      $this->log("stdout: $stdout");
    }
    if (!empty($stderr) && $log) {
      $this->log("stderr: $stderr");
    }
    $returnCode = call_user_func_array($this->proc_close, [$process]);
    return $returnCode;
  }

  /**
   * Sets the function to call for getting the contents.
   *
   * @param $callable
   *   A callable function.
   */
  public function setContentGetter($callable) {
    $this->contentGetter = $callable;
  }

  protected function getContents($res) {
    $func = $this->contentGetter;
    return $func($res);
  }

  public function setPipes(array $pipes) {
    $this->pipes = $pipes;
  }

  public function getPipes() {
    return $this->pipes;
  }

  /**
   * @param string $chdirCommand
   */
  public function setChdirCommand($chdirCommand) {
    $this->chdirCommand = $chdirCommand;
  }

  /**
   * @param string $proc_close
   */
  public function setProcClose($proc_close) {
    $this->proc_close = $proc_close;
  }

  /**
   * @param string $proc_open
   */
  public function setProcOpen($proc_open) {
    $this->proc_open = $proc_open;
  }

  protected function debug($message) {
    if ($this->verbose) {
      $this->log($message);
    }
  }

  /**
   * Log a message.
   *
   * @param string $message
   */
  protected function log($message) {
    if ($this->verbose) {
      print_r("$message\n");
    }
    $this->messages[] = new Message($message);
  }

  /**
   * Does a composer install.
   *
   * @throws \eiriksm\CosyComposer\Exceptions\ComposerInstallException
   */
  protected function doComposerInstall() {
    // First copy the custom cache in here.
    if (file_exists($this->createCacheDir())) {
      $this->log('Found custom cache using this for vendor folder.');
      $this->execCommand(sprintf('rsync -a %s/* %s/', $this->createCacheDir(), $this->tmpDir));
    }
    // @todo: Should probably use composer install command programatically.
    if ($code = $this->execCommand('composer install', FALSE)) {
      // Other status code than 0.
      throw new ComposerInstallException('Composer install failed with exit code ' . $code);
    }
  }

  /**
   * Changes to a different directory.
   */
  private function chdir($dir) {
    return call_user_func($this->chdirCommand, $dir);
  }

  /**
   * @return string
   */
  public function getTmpDir() {
    return $this->tmpDir;
  }

  /**
   * @param string $tmpDir
   */
  public function setTmpDir($tmpDir) {
    $this->tmpDir = $tmpDir;
  }

  /**
   * @param $package_name
   * @param $lockdata
   */
  public function retrieveChangeLog($package_name, $lockdata, $version_from, $version_to) {
    $data = $this->getPackageData($package_name, $lockdata);
    $clone_path = $this->retrieveDependencyRepo($data);
    // Then try to get the changelog.
    if ($data->{'notification-url'} == 'https://packages.drupal.org/8/downloads') {
      // Convert it, slightly.
      $version_from = $this->convertComposerVersionToDrupalTag($version_from);
      $version_to = $this->convertComposerVersionToDrupalTag($version_to);
    }
    if ($data->{'notification-url'} == 'https://packages.drupal.org/7/downloads') {
      // Convert it, slightly.
      $version_from = $this->convertComposerVersionToDrupalTag($version_from, 7);
      $version_to = $this->convertComposerVersionToDrupalTag($version_to, 7);
    }
    $command = sprintf('git -C %s log %s..%s --oneline', $clone_path, $version_from, $version_to);
    $this->execCommand($command);
    $changelog_string = $this->getLastStdOut();
    if (empty($changelog_string)) {
      throw new \Exception('The changelog string was empty');
    }
    // Then split it into lines that makes sense.
    $log = ChangeLogData::createFromString($changelog_string);
    // Then assemble the git source.
    $git_url = preg_replace('/.git$/', '', $data->source->url);
    $log->setGitSource($git_url);
    return $log;
  }

  private function retrieveDependencyRepo($data) {
    // First find the repo source.
    if (!isset($data->source) || $data->source->type != 'git') {
      throw new \Exception('Unknown source or non-git source. Aborting.');
    }
    // We could have this cached in the md5 of the package name.
    $clone_path = '/tmp/' . md5($data->name);
    $repo_path = $data->source->url;
    if (!file_exists($clone_path)) {
      $this->execCommand(sprintf('git clone %s %s', $repo_path, $clone_path));
    }
    else {
      $this->execCommand(sprintf('git -C %s pull', $clone_path));
    }
    return $clone_path;
  }

  private function getPackageData($package_name, $lockdata) {
    $lockfile_key = 'packages';
    $key = $this->getPackagesKey($package_name, $lockfile_key, $lockdata);
    if ($key === FALSE) {
      // Well, could be a dev req.
      $lockfile_key = 'packages-dev';
      $key = $this->getPackagesKey($package_name, $lockfile_key, $lockdata);
      // If the key still is false, then this is not looking so good.
      if ($key === FALSE) {
        throw new \Exception('Did not find the requested package in the lockfile. This is probably an error');
      }
    }
    return $lockdata->{$lockfile_key}[$key];
  }

  private function getPackagesKey($package_name, $lockfile_key, $lockdata) {
    $names = array_column($lockdata->{$lockfile_key}, 'name');
    return array_search($package_name, $names);
  }

  /**
   * Converts a composer version to a drupal tag.
   *
   * Like for example:
   * Composer tag 1.19.0 becomes 8.x-1.19
   */
  private function convertComposerVersionToDrupalTag($version, $drupal_version = 8) {
    $parts = explode('.', $version);
    return sprintf('%d.x-%d.%d', $drupal_version, $parts[0], $parts[1]);
  }
}
