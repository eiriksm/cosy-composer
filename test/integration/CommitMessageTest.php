<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test for a default commit message.
 */
class CommitMessageTest extends ComposerUpdateIntegrationBase
{
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.0.2';
    protected $composerAssetFiles = 'composer-commit';
    protected $hasCorrectCommit = false;

    public function tearDown()
    {
        putenv('USE_NEW_COMMIT_MSG=');
    }
    
    public function testCommitMessage()
    {
        $this->runtestExpectedOutput();
        self::assertEquals($this->hasCorrectCommit, true);
    }

    public function testNewCommit()
    {
        putenv('USE_NEW_COMMIT_MSG=1');
        $this->runtestExpectedOutput();
        self::assertEquals($this->hasCorrectCommit, true);
    }

    protected function handleExecutorReturnCallback($cmd, &$return)
    {
        $cmd_string = implode(' ', $cmd);
        if (strpos($cmd_string, $this->getCorrectCommit()) !== false) {
            $this->hasCorrectCommit = true;
        }
    }

    protected function getCorrectCommit()
    {
        return 'git commit composer.json composer.lock -m Update psr/log';
    }
}
