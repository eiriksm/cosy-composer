<?php

namespace eiriksm\CosyComposer\Providers;

use eiriksm\CosyComposer\ProviderInterface;
use Github\Api\Issue;
use Github\Api\PullRequest;
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

    public function authenticate($user, $token)
    {
        $this->client->authenticate($user, null, Client::AUTH_HTTP_TOKEN);
    }

    public function authenticatePrivate($user, $token)
    {
        $this->client->authenticate($user, null, Client::AUTH_HTTP_TOKEN);
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

    public function getPrsNamed(Slug $slug)
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
        /** @var PullRequest $prs */
        $prs = $this->client->api('pull_request');
        $data = $prs->create($user_name, $user_repo, $params);
        if (!empty($params['assignees'])) {
            // Now try to update it with assignees.
            try {
                /** @var Issue $issues */
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
}
