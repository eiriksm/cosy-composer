<?php

namespace eiriksm\CosyComposer;

use eiriksm\CosyComposer\Exceptions\GitPushException;
use eiriksm\ViolinistMessages\UpdateListItem;
use Symfony\Component\Yaml\Yaml;
use Violinist\CommitMessageCreator\Constant\Type;
use Violinist\CommitMessageCreator\Creator;
use Violinist\Config\Config;

trait GitCommandsTrait
{
    /**
     * @var string
     */
    protected $commitMessage;

    protected function switchBranch($branch_name)
    {
        $this->log('Checking out new branch: ' . $branch_name);
        $result = $this->execCommand(['git', 'checkout', '-b', $branch_name], false);
        if ($result) {
            $this->log($this->getLastStdErr());
            throw new \Exception(sprintf('There was an error checking out branch %s. Exit code was %d', $branch_name, $result));
        }
        // Make sure we do not have any uncommitted changes.
        $this->execCommand(['git', 'checkout', '.'], false);
    }

    protected function cleanRepoForCommit()
    {
        // Clean up the composer.lock file if it was not part of the repo.
        $this->execCommand(['git', 'clean', '-f', 'composer.*']);
    }

    protected function commitFiles($msg, ?UpdateListItem $item = null)
    {
        $command = array_filter([
            'git', "commit",
            'composer.json',
            $this->getlockFileContents() ? 'composer.lock' : '',
            '-m',
            $msg,
        ]);
        if ($item) {
            $command[] = '-m';
            $command[] = sprintf("%s\n%s", Helpers::getCommitMessageSeparator(), Yaml::dump([
                'update_data' => [
                    'package' => $item->getPackageName(),
                    'from' => $item->getOldVersion(),
                    'to' => $item->getNewVersion(),
                ],
            ]));
        }
        if ($this->execCommand($command, false, 120)) {
            $this->log($this->getLastStdOut());
            $this->log($this->getLastStdErr());
            throw new \Exception('Error committing the composer files. They are probably not changed.');
        }
        $this->commitMessage = $msg;
    }

    protected function getCommitCreator(Config $config) : Creator
    {
        $creator = new Creator();
        $type = Type::NONE;
        $creator->setType($type);
        try {
            $creator->setType($config->getCommitMessageConvention());
        } catch (\InvalidArgumentException $e) {
            // Fall back to using none.
        }
        return $creator;
    }

    protected function commitFilesForPackage(UpdateListItem $item, Config $config, $is_dev = false)
    {
        $this->cleanRepoForCommit();
        $creator = $this->getCommitCreator($config);
        $msg = $creator->generateMessage($item, $is_dev);
        $this->commitFiles($msg, $item);
    }

    protected function pushCode($branch_name, $default_base, $lock_file_contents)
    {
        if ($this->isPrivate) {
            $origin = 'origin';
            if ($this->execCommand(["git", 'push', $origin, $branch_name, '--force'])) {
                $this->log($this->getLastStdOut());
                $this->log($this->getLastStdErr());
                throw new GitPushException('Could not push to ' . $branch_name);
            }
        } else {
            $this->preparePrClient();
            /** @var eiriksm\CosyComposer\Providers\PublicGithubWrapper $this_client */
            $this_client = $this->client;
            $this_client->forceUpdateBranch($branch_name, $default_base);
            $msg = $this->commitMessage;
            $this_client->commitNewFiles($this->tmpDir, $default_base, $branch_name, $msg, $lock_file_contents);
        }
    }

    protected function createPullrequest($pr_params)
    {
        $this->log('Creating pull request from ' . $pr_params['head']);
        return $this->getPrClient()->createPullRequest($this->slug, $pr_params);
    }
}
