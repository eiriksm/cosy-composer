<?php

namespace eiriksm\CosyComposer;

use Violinist\Slug\Slug;

interface ProviderInterface
{
    public function authenticate($user, $token);

    public function authenticatePrivate($user, $token);

    public function repoIsPrivate(Slug $slug);

    public function getDefaultBranch(Slug $slug);

    public function getBranchesFlattened(Slug $slug);

    public function getPrsNamed(Slug $slug);

    public function getDefaultBase(Slug $slug, $default_branch);

    public function createFork($user, $repo, $fork_user);

    /**
     * @param Slug $slug
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
    public function createPullRequest(Slug $slug, $params);

    public function updatePullRequest(Slug $slug, $id, $params);
}
