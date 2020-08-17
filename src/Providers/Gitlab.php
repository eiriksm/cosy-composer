<?php

namespace eiriksm\CosyComposer\Providers;

use eiriksm\CosyComposer\ProviderInterface;
use Gitlab\Api\MergeRequests;
use Gitlab\Client;
use Gitlab\ResultPager;
use Violinist\Slug\Slug;

class Gitlab implements ProviderInterface
{

    protected $client;

    private $cache;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function authenticate($user, $token)
    {
        $this->client->authenticate($user, Client::AUTH_OAUTH_TOKEN);
    }

    public function authenticatePrivate($user, $token)
    {
        $this->client->authenticate($user, Client::AUTH_OAUTH_TOKEN);
    }

    public function repoIsPrivate(Slug $slug)
    {
        // Consider all gitlab things private, since we have the API key to do so anyway.
        return true;
    }

    public function getDefaultBranch(Slug $slug)
    {
        $url = $slug->getUrl();
        if (!isset($this->cache['repo'])) {
            $this->cache['repo'] = $this->client->api('projects')->show(self::getProjectId($url));
        }
        return $this->cache['repo']['default_branch'];
    }

    protected function getBranches(Slug $slug)
    {
        if (!isset($this->cache['branches'])) {
            $pager = new ResultPager($this->client);
            $api = $this->client->api('repo');
            $method = 'branches';
            $this->cache['branches'] = $pager->fetchAll($api, $method, [self::getProjectId($slug->getUrl())]);
        }
        return $this->cache['branches'];
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

    public function getPrsNamed(Slug $slug)
    {
        $pager = new ResultPager($this->client);
        $api = $this->client->api('mr');
        $method = 'all';
        $prs = $pager->fetchAll($api, $method, [self::getProjectId($slug->getUrl())]);
        $prs_named = [];
        foreach ($prs as $pr) {
            if ($pr['state'] != 'opened') {
                continue;
            }
            // Now get the last commits for this branch.
            $commits = $this->client->api('repo')->commits(self::getProjectId($slug->getUrl()), [
                'ref_name' => $pr['source_branch'],
            ]);
            $prs_named[$pr['source_branch']] = [
                'title' => $pr['title'],
                'number' => $pr["iid"],
                'base' => [
                    'sha' => !empty($commits[1]["id"]) ? $commits[1]["id"] : $pr['sha'],
                ],
            ];
        }
        return $prs_named;
    }

    public function getDefaultBase(Slug $slug, $default_branch)
    {
        $branches = $this->getBranches($slug);
        $default_base = null;
        foreach ($branches as $branch) {
            if ($branch['name'] == $default_branch) {
                $default_base = $branch['commit']['id'];
            }
        }
        return $default_base;
    }

    public function createFork($user, $repo, $fork_user)
    {
        throw new \Exception('Gitlab integration only support creating PRs as the authenticated user.');
    }

    public function createPullRequest(Slug $slug, $params)
    {
        /** @var MergeRequests $mr */
        $mr = $this->client->api('mr');
        $assignee = null;
        $data = $mr->create(self::getProjectId($slug->getUrl()), $params['head'], $params['base'], $params['title'], $assignee, null, $params['body']);
        if (!empty($data['web_url'])) {
            $data['html_url'] = $data['web_url'];
        }
        // Try to update with assignees.
        if (!empty($params['assignees'])) {
            $new_data = [
                'assignee_ids' => $params['assignees'],
            ];
            $mr->update(self::getProjectId($slug->getUrl()), $data["iid"], $new_data);
        }
        return $data;
    }

    public function updatePullRequest(Slug $slug, $id, $params)
    {

        $gitlab_params = [
            'source_branch' => $params['head'],
            'target_branch' => $params['base'],
            'title' => $params['title'],
            'assignee_id' => $params['assignees'],
            'target_project_id' => null,
            'description' => $params['body'],
        ];
        return $this->client->api('mr')->update(self::getProjectId($slug->getUrl()), $id, $gitlab_params);
    }

    public static function getProjectId($url)
    {
        $url = parse_url($url);
        return ltrim($url['path'], '/');
    }
}
