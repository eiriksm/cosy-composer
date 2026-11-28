<?php

namespace eiriksm\CosyComposer\Providers;

use eiriksm\CosyComposer\Exceptions\PullRequestNotOpenException;
use Github\AuthMethod;
use Github\Client;
use Github\ResultPager;
use Violinist\Slug\Slug;

class Github extends AbstractProvider
{
    const PULL_REQUEST_STATE_OPEN = 'open';
    const PULL_REQUEST_STATE_CLOSED = 'closed';

    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function addLabels(array $pr_data, Slug $slug, array $labels) : bool
    {
        if (!isset($pr_data["number"])) {
            return false;
        }
        try {
            $data = $this->client->issue()->labels()->add($slug->getUserName(), $slug->getUserRepo(), $pr_data['number'], $labels);
            if (empty($data[0]["id"])) {
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function enableAutomerge(array $pr_data, Slug $slug, $merge_method = self::MERGE_METHOD_MERGE) : bool
    {
        if (!isset($pr_data["node_id"])) {
            return false;
        }
        // https://docs.github.com/en/graphql/reference/enums#pullrequestmergemethod
        $api_merge_method = 'MERGE';
        if ($merge_method === self::MERGE_METHOD_REBASE) {
            $api_merge_method = 'REBASE';
        }
        if ($merge_method === self::MERGE_METHOD_SQUASH) {
            $api_merge_method = 'SQUASH';
        }
        try {
            $data = $this->client->api('graphql')->execute('mutation MyMutation ($input: EnablePullRequestAutoMergeInput!) {
  enablePullRequestAutoMerge(input: $input) {
    pullRequest {
      id
    }
  }
}', [
            'input' => [
                'pullRequestId' => $pr_data['node_id'],
                'mergeMethod' => $api_merge_method,
            ],
            ]);
        } catch (\Throwable $e) {
            return false;
        }
        if (!empty($data["errors"])) {
            return false;
        }
        return true;
    }

    public function authenticate(string $user, ?string $token) : void
    {
        $this->client->authenticate($user, null, AuthMethod::ACCESS_TOKEN);
    }

    public function authenticatePrivate(string $user, ?string $token) : void
    {
        $this->client->authenticate($user, null, AuthMethod::ACCESS_TOKEN);
    }

    public function repoIsPrivate(Slug $slug)
    {
        return (bool) $this->getRepo($slug)['private'];
    }

    protected function getRepo(Slug $slug) : array
    {
        return $this->wrapLookupInCache(
            $this->getRepoCacheKey(self::REPO_CACHE_KEY, $slug),
            fn () => $this->client->api('repo')->show($slug->getUserName(), $slug->getUserRepo())
        );
    }

    public function getDefaultBranch(Slug $slug)
    {
        return $this->getRepo($slug)['default_branch'];
    }

    /**
     * @return array<mixed>
     */
    protected function getBranches(Slug $slug) : array
    {
        return $this->wrapLookupInCache(
            $this->getRepoCacheKey(self::BRANCHES_CACHE_KEY, $slug),
            function () use ($slug) {
                $pager = new ResultPager($this->client);
                $api = $this->client->api('repo');
                return $pager->fetchAll($api, 'branches', [$slug->getUserName(), $slug->getUserRepo()]);
            }
        );
    }

    public function getBranchesFlattened(Slug $slug)
    {
        $branches = $this->getBranches($slug);

        $branches_flattened = [];
        foreach ($branches as $branch) {
            $branches_flattened[] = $branch['name'];
        }
        return $branches_flattened;
    }

    public function getPrsNamed(Slug $slug) : NamedPrs
    {
        $user = $slug->getUserName();
        $repo = $slug->getUserRepo();
        $pager = new ResultPager($this->client);
        $api = $this->client->api('pr');
        $method = 'all';
        $prs = $pager->fetchAll($api, $method, [$user, $repo]);
        $prs_named = new NamedPrs();
        foreach ($prs as $pr) {
            $prs_named->addFromPrData($pr);
            // Attempt to retrieve the actual commit.
            try {
                /** @var \Github\Api\Repository\Commits $commits */
                $commits = $this->client->api('repo')->commits();
                $commit = $commits->show($user, $repo, $pr['head']['sha']);
                if (!empty($commit["commit"]["message"])) {
                    $prs_named->addFromCommit($commit["commit"]["message"], $pr);
                }
            } catch (\Exception $e) {
                // If the commit is not found, we just skip it.
            }
        }
        return $prs_named;
    }

    public function getAuthenticatedUsername() : ?string
    {
        try {
            return $this->wrapLookupInCache(self::AUTHENTICATED_USERNAME_CACHE_KEY, function () {
                $user = $this->client->api('current_user')->show();
                return $user['login'] ?? null;
            });
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getDefaultBase(Slug $slug, $default_branch)
    {
        $branches = $this->getBranches($slug);
        $default_base = null;
        foreach ($branches as $branch) {
            if ($branch['name'] == $default_branch) {
                $default_base = $branch['commit']['sha'];
            }
        }
        return $default_base;
    }

    public function getDefaultBaseTimestamp(Slug $slug, string $default_branch) : ?string
    {
        $user = $slug->getUserName();
        $repo = $slug->getUserRepo();
        try {
            $branch = $this->client->api('repo')->branches($user, $repo, $default_branch);
            if (!empty($branch['commit']['commit']['committer']['date'])) {
                return $branch['commit']['commit']['committer']['date'];
            }
        } catch (\Exception $e) {
            // If the branch is not found, we just return null.
        }
        return null;
    }

    public function createFork($user, $repo, $fork_user)
    {
        return $this->client->api('repo')->forks()->create($user, $repo, [
          'organization' => $fork_user,
        ]);
    }

    public function createPullRequest(Slug $slug, $params)
    {
        $user_name = $slug->getUserName();
        $user_repo = $slug->getUserRepo();
        /** @var \Github\Api\PullRequest $prs */
        $prs = $this->client->api('pull_request');
        $data = $prs->create($user_name, $user_repo, $params);
        if (!empty($params['assignees'])) {
            // Now try to update it with assignees.
            try {
                /** @var \Github\Api\Issue $issues */
                $issues = $this->client->api('issues');
                $issues->update($user_name, $user_repo, $data['number'], [
                    'assignees' => $params['assignees'],
                ]);
            } catch (\Exception $e) {
                // Too bad.
                //  @todo: Should be possible to inject a logger and log this.
            }
        }
        return $data;
    }

    public function updatePullRequest(Slug $slug, $id, $params)
    {
        $user_name = $slug->getUserName();
        $user_repo = $slug->getUserRepo();
        return $this->client->api('pull_request')->update($user_name, $user_repo, $id, $params);
    }

    public function closePullRequestWithComment(Slug $slug, $pr_id, $comment) : void
    {
        // The pull request might have been merged or closed since we listed it.
        // Closing it again does not fail on GitHub, so we check the state
        // first. We do not want a comment saying it was superseded on a pull
        // request that is not open.
        $pr = $this->client->api('pull_request')->show($slug->getUserName(), $slug->getUserRepo(), $pr_id);
        $state = $pr['state'] ?? null;
        if ($state !== self::PULL_REQUEST_STATE_OPEN) {
            throw new PullRequestNotOpenException(sprintf('Pull request %s is not open (state: %s)', $pr_id, $state ?? 'unknown'));
        }
        $this->client->api('pull_request')->update($slug->getUserName(), $slug->getUserRepo(), $pr_id, [
            'state' => self::PULL_REQUEST_STATE_CLOSED,
        ]);
        $this->client->api('issue')->comments()->create($slug->getUserName(), $slug->getUserRepo(), $pr_id, [
            'body' => $comment,
        ]);
    }
}
