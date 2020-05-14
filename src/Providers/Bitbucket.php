<?php

namespace eiriksm\CosyComposer\Providers;

use Bitbucket\Client;
use eiriksm\CosyComposer\ProviderInterface;

class Bitbucket implements ProviderInterface
{

    private $cache;

    /**
     * @var Client
     */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function authenticate($user, $token)
    {
        $this->client->authenticate(Client::AUTH_OAUTH_TOKEN, $user);
    }

    public function authenticatePrivate($user, $token)
    {
        $this->client->authenticate(Client::AUTH_OAUTH_TOKEN, $user);
    }

    public function repoIsPrivate($user, $repo)
    {
        if (!isset($this->cache['repo'])) {
            $this->cache['repo'] = $this->client->repositories()->users($user)->show($repo);
        }
        return (bool) $this->cache["repo"]["is_private"];
    }

    public function getDefaultBranch($user, $repo)
    {
        if (!isset($this->cache['repo'])) {
            $this->cache['repo'] = $this->client->repositories()->users($user)->show($repo);
        }
        if (empty($this->cache["repo"]["mainbranch"]["name"])) {
            throw new \Exception('No default branch found');
        }
        return $this->cache["repo"]["mainbranch"]["name"];
    }

    protected function getBranches($user, $repo)
    {
        if (!isset($this->cache['branches'])) {
            $repo_users = $this->client->repositories()->users($user);
            $repo_users->setPerPage(1000);
            $this->cache['branches'] = $repo_users->refs($repo)->branches()->list();
        }
        return $this->cache["branches"]["values"];
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
        $repo_users = $this->client->repositories()->users($user);
        $repo_users->setPerPage(1000);
        $prs = $repo_users->pullRequests($repo)->list();
        $prs_named = [];
        foreach ($prs["values"] as $pr) {
            if ($pr["state"] != 'OPEN') {
                continue;
            }
            $prs_named[$pr["source"]["branch"]["name"]] = [
                'base' => [
                    'sha' => $pr["destination"]["commit"]["hash"],
                ],
                'number' => $pr["id"],
                'title' => $pr["title"],
            ];
        }
        return $prs_named;
    }

    public function getDefaultBase($user, $repo, $default_branch)
    {
        $branches = $this->getBranches($user, $repo);
        $default_base = null;
        foreach ($branches as $branch) {
            if ($branch['name'] == $default_branch) {
                $default_base = $branch["target"]["hash"];
            }
        }
        // Since the braches only gives us 12 characters, we need to trim the default base to the same.
        return substr($default_base, 0, 12);
    }

    public function createFork($user, $repo, $fork_user)
    {
        throw new \Exception('Gitlab integration only support creating PRs as the authenticated user.');
    }

    public function createPullRequest($user_name, $user_repo, $params)
    {
        $bitbucket_params = [
            'title' => $params['title'],
            'source' => [
                'branch' => [
                    'name' => $params["head"],
                ]
            ],
            'destination' => [
                'branch' => [
                    'name' => $params["base"],
                ],
            ],
            'description' => $params['body'],
        ];

        if (!empty($params['assignees'])) {
            foreach ($params['assignees'] as $assignee) {
                $bitbucket_params['reviewers'][] = [
                    'username' => $assignee,
                ];
            }
        }
        $data = $this->client->repositories()->users($user_name)->pullRequests($user_repo)->create($bitbucket_params);
        if (!empty($data["links"]["html"]["href"])) {
            $data['html_url'] = $data["links"]["html"]["href"];
        }
        return $data;
    }

    public function updatePullRequest($user_name, $user_repo, $id, $params)
    {
        return $this->client->repositories()->users($user_name)->pullRequests($user_repo)->update($id, $params);
    }
}
