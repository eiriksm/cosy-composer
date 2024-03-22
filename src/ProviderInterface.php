<?php

namespace eiriksm\CosyComposer;

use Violinist\Slug\Slug;

interface ProviderInterface
{
    const MERGE_METHOD_MERGE = 'merge';
    const MERGE_METHOD_REBASE = 'rebase';
    const MERGE_METHOD_SQUASH = 'squash';

    public function addLabels(array $pr_data, Slug $slug, array $labels) : bool;

    public function enableAutomerge(array $pr_data, Slug $slug, $merge_method = self::MERGE_METHOD_MERGE) : bool;

    public function authenticate($user, $token);

    public function authenticatePrivate($user, $token);

    public function repoIsPrivate(Slug $slug);

    public function getDefaultBranch(Slug $slug);

    public function getBranchesFlattened(Slug $slug);

    /**
     * @param Slug $slug
     * @return array
     */
    public function getPrsNamed(Slug $slug) : array;

    public function getDefaultBase(Slug $slug, $default_branch);

    public function createFork($user, $repo, $fork_user);

    public function closePullRequestWithComment(Slug $slug, $pr_id, $comment);

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
