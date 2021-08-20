<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test for commit message type coventional commits.
 */
class CommitMessageConventionalDevTest extends CommitMessageTest
{
    protected $composerAssetFiles = 'composer-conventional-dev';

    protected function getCorrectCommit()
    {
        return 'git commit composer.json composer.lock -m "build(deps): Update psr/log from 1.0.0 to 1.0.2"';
    }
}
