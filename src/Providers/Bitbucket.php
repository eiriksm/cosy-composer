<?php

namespace eiriksm\CosyComposer\Providers;

use Bitbucket\Api\Repositories;
use Bitbucket\Client;
use Bitbucket\Exception\ValidationFailedException;
use Bitbucket\ResultPager;
use eiriksm\CosyComposer\Message;
use eiriksm\CosyComposer\ProviderInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Violinist\Slug\Slug;

class Bitbucket implements ProviderInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    const MERGE_REQUEST_STATE_OPEN = 'OPEN';

    private $cache;

    /**
     * @var Client
     */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->logger = new NullLogger();
    }

    public function authenticate(string $user, ?string $token) : void
    {
        if ($user && $token) {
            $this->client->authenticate(Client::AUTH_HTTP_PASSWORD, $user, $token);
        } else {
            $this->client->authenticate(Client::AUTH_OAUTH_TOKEN, $user);
        }
    }

    public function authenticatePrivate(string $user, ?string $token) : void
    {
        $this->client->authenticate(Client::AUTH_OAUTH_TOKEN, $user);
    }

    public function repoIsPrivate(Slug $slug)
    {
        $user = $slug->getUserName();
        $repo = $slug->getUserRepo();
        if (!isset($this->cache['repo'])) {
            $this->cache['repo'] = $this->getRepo($user, $repo);
        }
        return (bool) $this->cache["repo"]["is_private"];
    }

    protected function getRepo($user, $repo)
    {
        return $this->client->repositories()->workspaces($user)->show($repo);
    }

    public function getDefaultBranch(Slug $slug)
    {
        $user = $slug->getUserName();
        $repo = $slug->getUserRepo();
        if (!isset($this->cache['repo'])) {
            $this->cache['repo'] = $this->getRepo($user, $repo);
        }
        if (empty($this->cache["repo"]["mainbranch"]["name"])) {
            throw new \Exception('No default branch found');
        }
        return $this->cache["repo"]["mainbranch"]["name"];
    }

    protected function getBranches($user, $repo)
    {
        if (!isset($this->cache['branches'])) {
            $paginator = new ResultPager($this->client);
            $repo_users = $this->client->repositories()->workspaces($user);
            $branch_client = $repo_users->refs($repo)->branches();

            $this->cache['branches'] = [
                'values' => $paginator->fetchAll($branch_client, 'list'),
            ];
        }
        return $this->cache["branches"]["values"];
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

    public function getPrsNamed(Slug $slug) : NamedPrs
    {
        $user = $slug->getUserName();
        $repo = $slug->getUserRepo();
        $api_repo = $this->client->repositories();
        $prs_client = $api_repo->workspaces($user)->pullRequests($repo);
        $paginator = new ResultPager($this->client);
        $prs = [
            'values' => $paginator->fetchAll($prs_client, 'list'),
        ];
        $prs_named = new NamedPrs();
        foreach ($prs["values"] as $pr) {
            if ($pr["state"] !== self::MERGE_REQUEST_STATE_OPEN) {
                continue;
            }
            $data = [
                'base' => [
                    'sha' => $this->getPrBaseSha($api_repo, $user, $repo, $pr),
                    'ref' => $pr["destination"]["branch"]["name"],
                ],
                'html_url' => $pr["links"]["html"]["href"],
                'number' => $pr["id"],
                'title' => $pr["title"],
                'user' => [
                    'login' => !empty($pr["author"]["uuid"]) ? $pr["author"]["uuid"] : null,
                ],
                'head' => [
                    'ref' => $pr["source"]["branch"]["name"],
                ],
            ];
            $prs_named->addFromPrData($data);
            try {
                // See if we can retrieve the commit as well.
                $commit = $api_repo->workspaces($user)->commit($repo)->show($pr["source"]["commit"]["hash"]);
                $prs_named->addFromCommit($commit["message"], $data);
            } catch (\Throwable $e) {
                // If the commit is not found, we just skip it.
            }
        }
        return $prs_named;
    }

    /**
     * Get the commit the PR branch is actually based on.
     *
     * On an open Bitbucket PR, destination.commit.hash follows the tip of the
     * destination branch, so it always matches the default base. Use the merge
     * base of the source and destination commits instead.
     *
     * @param array<mixed> $pr
     */
    protected function getPrBaseSha(Repositories $api_repo, string $user, string $repo, array $pr) : string
    {
        $destination = $pr["destination"]["commit"]["hash"];
        if (empty($pr["source"]["commit"]["hash"])) {
            return $destination;
        }
        try {
            $merge_base = $api_repo->workspaces($user)
                ->mergeBases($repo)
                ->show(sprintf('%s..%s', $pr["source"]["commit"]["hash"], $destination));
            if (!empty($merge_base['hash'])) {
                // Match the 12 character hashes getDefaultBase() returns.
                return substr($merge_base['hash'], 0, 12);
            }
        } catch (\Throwable $e) {
            // Fall back to the destination commit.
        }
        return $destination;
    }

    public function getDefaultBase(Slug $slug, $default_branch)
    {
        $user = $slug->getUserName();
        $repo = $slug->getUserRepo();
        $branches = $this->getBranches($user, $repo);
        $default_base = null;
        foreach ($branches as $branch) {
            if ($branch['name'] == $default_branch) {
                $default_base = $branch["target"]["hash"];
            }
        }
        // Since the branches only gives us 12 characters, we need to trim the default base to the same.
        return substr($default_base, 0, 12);
    }

    public function getAuthenticatedUsername() : ?string
    {
        if (!isset($this->cache['authenticated_username'])) {
            try {
                $user = $this->client->currentUser()->show();
                $this->cache['authenticated_username'] = $user['uuid'] ?? null;
            } catch (\Throwable $e) {
                return null;
            }
        }
        return $this->cache['authenticated_username'];
    }

    public function getDefaultBaseTimestamp(Slug $slug, string $default_branch) : ?string
    {
        $user = $slug->getUserName();
        $repo = $slug->getUserRepo();
        $branches = $this->getBranches($user, $repo);
        foreach ($branches as $branch) {
            if ($branch['name'] === $default_branch) {
                if (!empty($branch['target']['date'])) {
                    return $branch['target']['date'];
                }
            }
        }
        return null;
    }

    public function createFork($user, $repo, $fork_user)
    {
        throw new \Exception('Bitbucket integration only support creating PRs as the authenticated user.');
    }

    public function createPullRequest(Slug $slug, $params)
    {
        $user_name = $slug->getUserName();
        $user_repo = $slug->getUserRepo();
        $bitbucket_params = [
            'title' => $params['title'],
            'source' => [
                'branch' => [
                    'name' => $params["head"],
                ],
            ],
            'destination' => [
                'branch' => [
                    'name' => $params["base"],
                ],
            ],
            'description' => $params['body'],
        ];

        // Bitbucket does not reject a pull request for a source and
        // destination that already has an open one. It updates that pull
        // request instead, and replaces its reviewers with the ones in the
        // payload. So when we push to the branch of an open pull request,
        // update it explicitly, and keep the reviewers it already has.
        $existing = $this->getOpenPullRequest($slug, $params['head'], $params['base']);
        if ($existing) {
            $this->logger->log('info', new Message(sprintf(
                'Found open Bitbucket pull request %s from %s to %s. Updating it instead of creating a new one',
                $existing['id'],
                $params['head'],
                $params['base']
            )));
            $data = $this->updatePullRequest($slug, $existing['id'], $params);
        } else {
            $reviewers = self::createReviewers([], $params['assignees'] ?? []);
            if ($reviewers) {
                $bitbucket_params['reviewers'] = $reviewers;
            }
            $data = $this->client
                ->repositories()
                ->workspaces($user_name)
                ->pullRequests($user_repo)
                ->create($bitbucket_params);
        }
        if (!empty($data["links"]["html"]["href"])) {
            $data['html_url'] = $data["links"]["html"]["href"];
        }
        if (!empty($data['id'])) {
            $data['number'] = $data['id'];
        }
        return $data;
    }

    /**
     * Update a pull request without removing its reviewers.
     *
     * Bitbucket removes all reviewers from a pull request when an update does
     * not include them, so send the existing ones along every time.
     */
    public function updatePullRequest(Slug $slug, $id, $params)
    {
        $prs_api = $this->client
            ->repositories()
            ->workspaces($slug->getUserName())
            ->pullRequests($slug->getUserRepo());
        $id = (string) $id;
        $current = $prs_api->show($id);
        $assignees = $params['assignees'] ?? [];
        $bitbucket_params = [
            'title' => $params['title'] ?? $current['title'],
            'reviewers' => self::createReviewers($current['reviewers'] ?? [], $assignees),
        ];
        if (isset($params['body'])) {
            $bitbucket_params['description'] = $params['body'];
        }
        if (!empty($params['base'])) {
            $bitbucket_params['destination'] = [
                'branch' => [
                    'name' => $params['base'],
                ],
            ];
        }
        // Bitbucket rejects the whole update when one of the reviewers can not
        // be added, for example because the account was deactivated, left the
        // workspace, or is the author of the pull request. It does not say
        // which one, so try with fewer reviewers until the update goes
        // through. The reviewers someone added by hand are kept for as long
        // as possible, since keeping them is the reason we send reviewers at
        // all. Updating the pull request matters more than either, though.
        $attempts = self::getReviewerAttempts($current['reviewers'] ?? [], $assignees);
        $this->logger->log('info', new Message(sprintf(
            'Updating Bitbucket pull request %s with %s',
            $id,
            array_key_first($attempts)
        )));
        $last_exception = null;
        foreach ($attempts as $description => $reviewers) {
            if ($last_exception) {
                $this->logger->log('info', new Message(sprintf(
                    'Bitbucket rejected the update of pull request %s (%s). Retrying with %s',
                    $id,
                    $last_exception->getMessage(),
                    $description
                )));
            }
            $bitbucket_params['reviewers'] = $reviewers;
            try {
                $data = $prs_api->update($id, $bitbucket_params);
            } catch (ValidationFailedException $e) {
                $last_exception = $e;
                continue;
            }
            if ($last_exception) {
                $this->logger->log('info', new Message(sprintf(
                    'Updated Bitbucket pull request %s with %s',
                    $id,
                    $description
                )));
            }
            return $data;
        }
        $this->logger->log('error', new Message(sprintf(
            'Could not update Bitbucket pull request %s with any set of reviewers. The last error was: %s',
            $id,
            $last_exception->getMessage()
        )));
        throw $last_exception;
    }

    /**
     * The sets of reviewers to try an update with, in order of preference.
     *
     * @param array<mixed> $current_reviewers
     *   Reviewers as Bitbucket returns them on a pull request.
     * @param array<mixed> $assignees
     *   Usernames from the project config.
     *
     * @return array<string, list<array<string, string>>>
     *   Sets of reviewers, keyed by a description for the log. A set that is
     *   the same as an earlier one is left out.
     */
    protected static function getReviewerAttempts(array $current_reviewers, array $assignees) : array
    {
        $candidates = [
            self::createReviewers($current_reviewers, $assignees),
            self::createReviewers($current_reviewers, []),
            self::createReviewers([], $assignees),
            [],
        ];
        $attempts = [];
        foreach ($candidates as $reviewers) {
            // Existing reviewers are sent by uuid, and configured assignees by
            // username, so the description tells the sets apart.
            $description = self::describeReviewers($reviewers);
            if (!isset($attempts[$description])) {
                $attempts[$description] = $reviewers;
            }
        }
        return $attempts;
    }

    /**
     * Describe a set of reviewers for the log.
     *
     * @param list<array<string, string>> $reviewers
     */
    protected static function describeReviewers(array $reviewers) : string
    {
        $existing = count(array_filter($reviewers, function (array $reviewer) {
            return isset($reviewer['uuid']);
        }));
        $assignees = count($reviewers) - $existing;
        $parts = [];
        if ($existing) {
            $parts[] = sprintf('%d existing reviewer(s)', $existing);
        }
        if ($assignees) {
            $parts[] = sprintf('%d configured assignee(s) as reviewer(s)', $assignees);
        }
        return $parts ? implode(' and ', $parts) : 'no reviewers';
    }

    /**
     * Find the open pull request from one branch to another, if any.
     *
     * @return array<mixed>|null
     */
    protected function getOpenPullRequest(Slug $slug, string $source, string $destination) : ?array
    {
        $prs_api = $this->client
            ->repositories()
            ->workspaces($slug->getUserName())
            ->pullRequests($slug->getUserRepo());
        $prs = $prs_api->list([
            'q' => sprintf(
                'source.branch.name = "%s" AND destination.branch.name = "%s" AND state = "%s"',
                addcslashes($source, '"\\'),
                addcslashes($destination, '"\\'),
                self::MERGE_REQUEST_STATE_OPEN
            ),
        ]);
        foreach ($prs['values'] ?? [] as $pr) {
            if (!empty($pr['id'])) {
                return $pr;
            }
        }
        return null;
    }

    /**
     * Combine the reviewers a pull request has with the configured assignees.
     *
     * @param array<mixed> $existing
     *   Reviewers as Bitbucket returns them on a pull request.
     * @param array<mixed> $assignees
     *   Usernames from the project config.
     *
     * @return list<array<string, string>>
     */
    public static function createReviewers(array $existing, array $assignees) : array
    {
        $reviewers = [];
        $known = [];
        foreach ($existing as $reviewer) {
            if (empty($reviewer['uuid'])) {
                continue;
            }
            $reviewers[] = [
                'uuid' => $reviewer['uuid'],
            ];
            foreach (['uuid', 'account_id', 'nickname', 'username'] as $key) {
                if (!empty($reviewer[$key])) {
                    $known[] = $reviewer[$key];
                }
            }
        }
        foreach ($assignees as $assignee) {
            if (in_array($assignee, $known, true)) {
                continue;
            }
            $reviewers[] = [
                'username' => $assignee,
            ];
            $known[] = $assignee;
        }
        return $reviewers;
    }

    public function addLabels(array $pr_data, Slug $slug, array $labels): bool
    {
        // @todo: Not implemented yet. It's also not supported on bitbucket, so.
        // https://jira.atlassian.com/browse/BCLOUD-11976
        return false;
    }

    public function enableAutomerge(array $pr_data, Slug $slug, $merge_method = self::MERGE_METHOD_MERGE) : bool
    {
        // @todo: Not implemented yet.
        return false;
    }

    public function closePullRequestWithComment(Slug $slug, $pr_id, $comment) : void
    {
        $this->client->repositories()->workspaces($slug->getUserName())->pullRequests($slug->getUserRepo())->comments($pr_id)->create([
            'content' => [
                'raw' => $comment,
            ],
        ]);
        $this->client->repositories()->workspaces($slug->getUserName())->pullRequests($slug->getUserRepo())->decline($pr_id);
    }

    public static function tokenIndicatesUserAppPassword($token)
    {
        return strlen($token) < 50 && strpos($token, ':') !== false;
    }

    public static function tokenIndicatesUserApiToken(string $token): bool
    {
        $api_token = self::getApiToken($token);
        return strlen($api_token) > 100 && str_starts_with($api_token, 'ATAT');
    }

    public static function getApiToken(string $token): string
    {
        if (strpos($token, ':') !== false) {
            [, $api_token] = explode(':', $token, 2);
            return $api_token;
        }
        return $token;
    }
}
