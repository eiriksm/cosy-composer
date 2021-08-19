<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test for issue 164.
 */
class CommitMessageTest extends ComposerUpdateIntegrationBase
{
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.0.2';
    protected $composerAssetFiles = 'composer-commit';
    protected $hasCorrectCommit = false;

    public function testRequireDevAdded()
    {
        $this->runtestExpectedOutput();
        self::assertEquals($this->hasCorrectCommit, true);
    }

    protected function handleExecutorReturnCallback($cmd, &$return)
    {
        if (strpos($cmd, $this->getCorrectCommit())) {
            $this->hasCorrectCommit = true;
        }
    }

    protected function getCorrectCommit()
    {
        return 'git commit composer.json composer.lock -m "Update psr/log"';
    }
}
