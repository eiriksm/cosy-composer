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

    protected function switchBranch($branch_name, $clean = true)
    {
        $this->log('Checking out new branch: ' . $branch_name);
        $result = $this->execCommand(['git', 'checkout', '-b', $branch_name], false);
        if ($result) {
            $this->log($this->getLastStdErr());
            throw new \Exception(sprintf('There was an error checking out branch %s. Exit code was %d', $branch_name, $result));
        }
        if ($clean) {
            // Make sure we do not have any uncommitted changes.
            $this->execCommand(['git', 'checkout', '.'], false);
        }
    }

    protected function cleanRepoForCommit()
    {
        // Clean up the composer.lock file if it was not part of the repo.
        $this->execCommand(['git', 'clean', '-f', 'composer.*']);
    }

    protected function commitFiles($msg, ?UpdateListItem $item = null, string $updateType = 'unknown')
    {
        $command = array_filter([
            'git', "commit",
            'composer.json',
            $this->getlockFileContents() ? 'composer.lock' : '',
            '-m',
            $msg,
        ]);
        $metadata = $this->getCommitMetadata($item, $updateType);
        if (!empty($metadata)) {
            $command[] = '-m';
            $command[] = sprintf("%s\n%s", Helpers::getCommitMessageSeparator(), Yaml::dump($metadata));
        }
        if ($this->execCommand($command, false, 120)) {
            $this->log($this->getLastStdOut());
            $this->log($this->getLastStdErr());
            throw new \Exception('Error committing the composer files. They are probably not changed.');
        }
        $this->commitMessage = $msg;
    }

    protected function getCommitMetadata(?UpdateListItem $item, string $updateType) : array
    {
        $metadata = [
            'violinist_metadata' => [
                'source' => 'violinist',
                'type' => $updateType,
            ],
        ];
        if ($item) {
            $metadata['update_data'] = [
                'package' => $item->getPackageName(),
                'from' => $item->getOldVersion(),
                'to' => $item->getNewVersion(),
            ];
        }
        return $metadata;
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
        $this->commitFiles($msg, $item, 'package');
    }

    protected function commitFilesForGroup(string $group_name, Config $config)
    {
        $this->cleanRepoForCommit();
        $creator = $this->getCommitCreator($config);
        $msg = $creator->generateMessageForGroup($group_name);
        $this->commitFiles($msg, null, 'group');
    }

    protected function pushCode($branch_name, $default_base, $lock_file_contents, string $default_branch)
    {
        if ($this->isPrivate) {
            $origin = 'origin';
            // Let's double check if we don't still have a PR that is actually
            // open, and is up to date.
            $main_sha = $this->getPrClient()->getDefaultBase($this->getSlug(), $default_branch);
            if ($main_sha !== $default_base) {
                throw new \Exception('The main branch has changed since we started. Aborting to be safe.');
            }
            // OK, now look at all the PRs we have, and see if we can find one
            // with the branch name we are about to push.
            $all_prs = $this->getPrClient()->getPrsNamed($this->getSlug());
            $all_of_them = $all_prs->getAllPrsNamed();
            if (!empty($all_of_them[$branch_name]["base"]["sha"])) {
                if ($all_of_them[$branch_name]["base"]["sha"] === $main_sha) {
                    $this->log('A pull request already exists for branch ' . $branch_name . ' and it is up to date. Not pushing any code.');
                    return;
                }
            }
            if ($this->execCommand(["git", 'push', $origin, $branch_name, '--force'])) {
                $this->log($this->getLastStdOut());
                $this->log($this->getLastStdErr());
                throw new GitPushException('Could not push to ' . $branch_name);
            }
        } else {
            $this->preparePrClient();
            /** @var \eiriksm\CosyComposer\Providers\PublicGithubWrapper $this_client */
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
