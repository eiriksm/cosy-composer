<?php

namespace eiriksm\CosyComposerTest\integration;

use Gitlab\Exception\RuntimeException;
use Violinist\Slug\Slug;

class GroupsPrExistsGitlabTest extends GroupsPrExistsTest
{

    protected function createPullRequest(Slug $slug, array $params)
    {
        throw new RuntimeException('The PR exists already yeah');
    }
}
