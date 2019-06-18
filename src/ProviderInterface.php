<?php

namespace eiriksm\CosyComposer;

interface ProviderInterface
{
    public function authenticate($user, $token);

    public function authenticatePrivate($user, $token);

    public function repoIsPrivate($user, $repo);

    public function getDefaultBranch($user, $repo);

    public function getBranchesFlattened($user, $repo);

    public function getPrsNamed($user, $repo);

    public function getDefaultBase($user, $repo, $default_branch);

    public function createFork($user, $repo, $fork_user);

    /**
     * @param string $user_name
     *   User name.
     * @param string $user_repo
     *   User repo.
     * @param array $params
     *   An array that consists of the following:
     *   - base (a base branch).
     *   - head (I think the branch name to pull in?)
     *   - title (PR title)
     *   - body (PR body)
     *   - assignees (an array of usernames (github) or user ids (gitlab). Gitlab only supports one assignee, so only
     *   the first element of the array will be used.
     *
     * @return mixed
     */
    public function createPullRequest($user_name, $user_repo, $params);

    public function updatePullRequest($user_name, $user_repo, $id, $params);
}
