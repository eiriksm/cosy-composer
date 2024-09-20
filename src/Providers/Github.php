<?php

namespace eiriksm\CosyComposer\Providers;

use eiriksm\CosyComposer\ProviderInterface;
use Github\AuthMethod;
use Github\Client;
use Github\ResultPager;
use Violinist\Slug\Slug;

class Github implements ProviderInterface
{

    private $cache;

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
        $data = $this->client->graphql()->execute('mutation MyMutation ($input: EnablePullRequestAutoMergeInput!) {
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
        if (!empty($data["errors"])) {
            return false;
        }
        return true;
    }

    public function authenticate($user, $token)
    {
        $this->client->authenticate($user, null, AuthMethod::ACCESS_TOKEN);
    }

    public function authenticatePrivate($user, $token)
    {
        $this->client->authenticate($user, null, AuthMethod::ACCESS_TOKEN);
    }

    public function repoIsPrivate(Slug $slug)
    {
        $user = $slug->getUserName();
        $repo = $slug->getUserRepo();
        if (!isset($this->cache['repo'])) {
            $this->cache['repo'] = $this->client->api('repo')->show($user, $repo);
        }
        return (bool) $this->cache['repo']['private'];
    }

    public function getDefaultBranch(Slug $slug)
    {
        $user = $slug->getUserName();
        $repo = $slug->getUserRepo();
        if (!isset($this->cache['repo'])) {
            $this->cache['repo'] = $this->client->api('repo')->show($user, $repo);
        }
        return $this->cache['repo']['default_branch'];
    }

    protected function getBranches($user, $repo)
    {
        if (!isset($this->cache['branches'])) {
            $pager = new ResultPager($this->client);
            $api = $this->client->api('repo');
            $method = 'branches';
            $this->cache['branches'] = $pager->fetchAll($api, $method, [$user, $repo]);
        }
        return $this->cache['branches'];
    }

    public function getBranchesFlattened(Slug $slug)
    {
        $user = $slug->getUserName();
        $repo = $slug->getUserRepo();
        $branches = $this->getBranches($user, $repo);

        $branches_flattened = [];
        foreach ($branches as $branch) {
            $branches_flattened[] = $branch['name'];
        }
        return $branches_flattened;
    }

    public function getPrsNamed(Slug $slug) : array
    {
        $user = $slug->getUserName();
        $repo = $slug->getUserRepo();
        $pager = new ResultPager($this->client);
        $api = $this->client->api('pr');
        $method = 'all';
        $prs = $pager->fetchAll($api, $method, [$user, $repo]);
        $prs_named = [];
        foreach ($prs as $pr) {
            $prs_named[$pr['head']['ref']] = $pr;
        }
        return $prs_named;
    }

    public function getDefaultBase(Slug $slug, $default_branch)
    {
        $user = $slug->getUserName();
        $repo = $slug->getUserRepo();
        $branches = $this->getBranches($user, $repo);
        $default_base = null;
        foreach ($branches as $branch) {
            if ($branch['name'] == $default_branch) {
                $default_base = $branch['commit']['sha'];
            }
        }
        return $default_base;
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

    public function closePullRequestWithComment(Slug $slug, $pr_id, $comment)
    {
        $this->client->issue()->comments()->create($slug->getUserName(), $slug->getUserRepo(), $pr_id, [
            'body' => $comment,
        ]);
        $this->client->api('pull_request')->update($slug->getUserName(), $slug->getUserRepo(), $pr_id, [
            'state' => 'closed',
        ]);
    }
}
