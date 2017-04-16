<?php

namespace eiriksm\CosyComposer;

use Composer\Command\OutdatedCommand;
use Composer\Console\Application;
use eiriksm\CosyComposer\Exceptions\ChdirException;
use eiriksm\CosyComposer\Exceptions\ComposerInstallException;
use eiriksm\CosyComposer\Exceptions\GitCloneException;
use Github\Client;
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

  private $proc_open = 'proc_open';

  private $proc_close = 'proc_close';

  private $pipes = [];

  private $contentGetter = 'stream_get_contents';

  private $githubUserName;
  private $githubUserPass;
  private $githubEmail;

  /**
   * CosyComposer constructor.
   * @param string $token
   * @param string $slug
   */
  public function __construct($token, $slug) {
    $this->token = $token;
    $this->slug = $slug;
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

  public function setForkUser($user) {
    $this->forkUser = $user;
  }

  public function run() {
    $this->log(sprintf('Starting update check for %s', $this->slug));
    $repo = $this->slug;
    $repo_parts = explode('/', $repo);
    $user_name = $repo_parts[0];
    $user_repo = $repo_parts[1];
    $tmpdir = uniqid();
    $this->tmpDir = sprintf('/tmp/%s', $tmpdir);
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
    // @todo: Check for the file as well.
    if (!$cdata = json_decode(file_get_contents($this->tmpDir . '/composer.json'))) {
      throw new \Exception('No composer in here');
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
    $branches = $pr_client->api('repo')->branches($branch_user, $user_repo);
    $branches_flattened = [];
    foreach ($branches as $branch) {
      $branches_flattened[] = $branch['name'];
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
        unset($data[$delta]);
      }
    }
    if (empty($data)) {
      $this->log('No updates that have not already been pushed.');
      $this->cleanup();
      return;
    }

    // If the repo is private, we need to push directly to the repo.
    // Unshallow the repo, for syncing it.
    $this->execCommand('git pull --unshallow');
    if (!$private) {
      $fork = $client->api('repo')->forks()->create($user_name, $user_repo, [
        'organization' => $this->forkUser,
      ]);
      $fork_url = sprintf('https://%s:%s@github.com/%s/%s', $this->githubUserName, $this->githubUserPass, $this->forkUser, $user_repo);
      $this->execCommand('git remote add fork ' . $fork_url, FALSE);
      // Sync the fork.
      $this->execCommand('git push fork ' . $default_branch);
    }
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
        // See where this package is.
        $req_command = 'require';
        if ($cdata->{'require-dev'}->{$item[0]}) {
          $req_command = 'require --dev';
        }
        // Create a new branch.
        $branch_name = $this->createBranchName($item);
        $this->execCommand('git checkout -b ' . $branch_name);
        $command = sprintf('composer %s %s:~%s', $req_command, $item[0], $item[2]);
        $this->execCommand($command);
        $command = 'composer update --with-dependencies ' . $item[0];
        $this->execCommand($command);
        $command = sprintf('GIT_AUTHOR_NAME="%s" GIT_AUTHOR_EMAIL="%s" GIT_COMMITTER_NAME="%s" GIT_COMMITTER_EMAIL="%s" git commit composer.* -m "Update %s"',
          $this->githubUserName,
          $this->githubEmail,
          $this->githubUserName,
          $this->githubEmail,
          $item[0]
        );
        $this->execCommand($command, FALSE);
        $origin = 'fork';
        if ($private) {
          $origin = 'origin';
        }
        if ($this->execCommand("git push $origin $branch_name")) {
          throw new \Exception('Could not push to ' . $branch_name);
        }
        $this->log('Creating pull request from ' . $branch_name);
        $head = $this->forkUser . ':' . $branch_name;
        if ($private) {
          $head = $branch_name;
        }
        $pullRequest = $pr_client->api('pull_request')->create($user_name, $user_repo, array(
          'base'  => $default_branch,
          'head'  => $head,
          'title' => $this->createTitle($item),
          'body'  => $this->createBody($item),
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
      $this->execCommand('git checkout ' . $default_branch);
    }
    // Clean up.
    $this->cleanUp();
  }

  /**
   * Get the messages that are logged
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
    $this->execCommand('rm -rf ' . $this->tmpDir, FALSE);
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
    return sprintf('Update %s from %s to %s', $item[0], $item[1], $item[2]);
  }

  protected function createBody($item) {
    // @todo: Make configurable.
    return sprintf("%s\n***\nThis is an automated pull request from [Violinist](https://violinist.io/): Continuously and automatically monitor and update your composer dependencies.", $this->createTitle($item));
  }

  protected function createBranchName($item) {
    $this->log('Creating branch name based on ' . print_r($item, TRUE));
    $item_string = sprintf('%s%s%s', $item[0], $item[1], $item[2]);
    // @todo: Fix this properly.
    $result = preg_replace('/[^a-zA-Z0-9]+/', '', $item_string);
    $this->log('Creating branch named ' . $result);
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
    $stderr = $this->getContents($pipes[2]);
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

  protected function debug() {
    if ($this->verbose) {
      $this->log(func_get_args());
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

  protected function doComposerInstall() {
    if ($code = $this->execCommand('composer install')) {
      // Other status code than 0.
      throw new ComposerInstallException('Composer install failed with exit code ' . $code);
    }
  }

  private function chdir($dir) {
    return call_user_func($this->chdirCommand, $dir);
  }

  public function getTmpDir() {
    return $this->tmpDir;
  }
}
