<?php

namespace eiriksm\CosyComposer\Providers;

use eiriksm\CosyComposer\ProviderInterface;
use Github\Client;
use Github\ResultPager;

class Github implements ProviderInterface
{

    private $cache;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function authenticate($user, $token)
    {
        $this->client->authenticate($user, null, Client::AUTH_URL_TOKEN);
    }

    public function authenticatePrivate($user, $token)
    {
        $this->client->authenticate($user, null, Client::AUTH_HTTP_TOKEN);
    }

    public function repoIsPrivate($user, $repo)
    {
        if (!isset($this->cache['repo'])) {
            $this->cache['repo'] = $this->client->api('repo')->show($user, $repo);
        }
        return (bool) $this->cache['repo']['private'];
    }

    public function getDefaultBranch($user, $repo)
    {
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

    public function getBranchesFlattened($user, $repo)
    {
        $branches = $this->getBranches($user, $repo);

        $branches_flattened = [];
        foreach ($branches as $branch) {
            $branches_flattened[] = $branch['name'];
        }
        return $branches_flattened;
    }

    public function getPrsNamed($user, $repo)
    {
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

    public function getDefaultBase($user, $repo, $default_branch)
    {
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

    public function createPullRequest($user_name, $user_repo, $params)
    {
        return $this->client->api('pull_request')->create($user_name, $user_repo, $params);
    }
}