<?php

namespace eiriksm\CosyComposerTest\integration;

use Gitlab\Exception\RuntimeException;
use Violinist\Slug\Slug;

class GroupsPrExistsConcurrentGitlabTest extends GroupsPrExistsConcurrentTest
{

    /**
     * @param array<mixed> $params
     * @return array<mixed>
     */
    protected function createPullRequest(Slug $slug, array $params)
    {
        throw new RuntimeException('The PR exists already yeah');
    }
}
