<?php

namespace eiriksm\CosyComposer\Providers;

use eiriksm\CosyComposer\ProviderInterface;
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

    public function authenticatePersonalAccessToken($user, $token)
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
            $this->cache['repo'] = $this->client->projects()->show(self::getProjectId($url));
        }
        return $this->cache['repo']['default_branch'];
    }

    protected function getBranches(Slug $slug)
    {
        if (!isset($this->cache['branches'])) {
            $pager = new ResultPager($this->client);
            $api = $this->client->repositories();
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

    public function getPrsNamed(Slug $slug) : NamedPrs
    {
        $pager = new ResultPager($this->client);
        $api = $this->client->mergeRequests();
        $method = 'all';
        $prs = $pager->fetchAll($api, $method, [self::getProjectId($slug->getUrl()), [
            'state' => 'opened',
        ]]);
        $prs_named = [];
        foreach ($prs as $pr) {
            if ($pr['state'] !== 'opened') {
                continue;
            }
            // Now get the last commits for this branch.
            $commits = $this->client->repositories()->commits(self::getProjectId($slug->getUrl()), [
                'ref_name' => $pr['source_branch'],
            ]);
            $prs_named[$pr['source_branch']] = [
                'title' => $pr['title'],
                'body' => !empty($pr['description']) ? $pr['description'] : '',
                'html_url' => !empty($pr['web_url']) ? $pr['web_url'] : '',
                'number' => $pr["iid"],
                'base' => [
                    'sha' => !empty($commits[1]["id"]) ? $commits[1]["id"] : $pr['sha'],
                    'ref' => $pr["target_branch"],
                ],
                'head' => [
                    'ref' => $pr['source_branch'],
                ],
            ];
        }
        return NamedPrs::createFromArray($prs_named);
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

    public function closePullRequestWithComment(Slug $slug, $pr_id, $comment)
    {
        $this->client->mergeRequests()->addNote(self::getProjectId($slug->getUrl()), $pr_id, $comment);
        $this->client->mergeRequests()->update(self::getProjectId($slug->getUrl()), $pr_id, [
            'state_event' => 'close',
        ]);
    }

    public function createPullRequest(Slug $slug, $params)
    {
        /** @var \Gitlab\Api\MergeRequests $mr */
        $mr = $this->client->mergeRequests();
        $data = $mr->create(self::getProjectId($slug->getUrl()), $params['head'], $params['base'], $params['title'], [
            'description' => $params['body'],
        ]);
        if (!empty($data['web_url'])) {
            $data['html_url'] = $data['web_url'];
        }
        if (!empty($data['iid'])) {
            $data['number'] = $data['iid'];
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
            'assignee_ids' => $params['assignees'],
            'target_project_id' => null,
            'description' => $params['body'],
        ];
        return $this->client->mergeRequests()->update(self::getProjectId($slug->getUrl()), $id, $gitlab_params);
    }

    public static function getProjectId($url)
    {
        $url = parse_url($url);
        if (empty($url['path'])) {
            $url['path'] = '/';
        }
        return ltrim($url['path'], '/');
    }

    public function addLabels(array $pr_data, Slug $slug, array $labels): bool
    {
        $gitlab_params = [
            'labels' => implode(',', $labels),
        ];
        $id = $pr_data['number'];
        try {
            $data = $this->client->mergeRequests()->update(self::getProjectId($slug->getUrl()), $id, $gitlab_params);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function enableAutomerge(array $pr_data, Slug $slug, $merge_method = self::MERGE_METHOD_MERGE) : bool
    {
        if (empty($pr_data['number']) && !empty($pr_data["iid"])) {
            $pr_data['number'] = $pr_data["iid"];
        }
        if ($merge_method === self::MERGE_METHOD_REBASE) {
            // Not supported on gitlab.
            return false;
        }
        $data = [
            'merge_when_pipeline_succeeds' => true,
        ];
        if ($merge_method === self::MERGE_METHOD_SQUASH) {
            $data['squash'] = true;
        }
        $project_id = self::getProjectId($slug->getUrl());
        $retries = 0;
        $mr = $this->client->mergeRequests();
        while (true) {
            try {
                $result = $mr->merge($project_id, $pr_data["number"], $data);
                if (!empty($result["merge_when_pipeline_succeeds"])) {
                    return true;
                }
            } catch (\Throwable $e) {
            }
            $retries++;
            if ($retries > 20) {
                return false;
            }
            // Sleep for 40 ms with a linear backoff. Max sleep time will be 800
            // ms. We want to keep it under 1 second because as it says in the
            // PHP documentation:
            // "Note: Values larger than 1000000 (i.e. sleeping for more than a
            // second) may not be supported by the operating system. Use sleep()
            // instead."
            usleep($retries * (1000 * 40));
        }
    }
}
