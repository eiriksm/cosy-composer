<?php

namespace eiriksm\CosyComposerTest\integration\issues;

use eiriksm\CosyComposerTest\integration\ComposerUpdateIntegrationBase;
use Violinist\Slug\Slug;

/**
 * Class Issue107Test.
 *
 * Issue 107: Exception on git commit should checkout files again.
 *
 * When a git commit fails, the code should run git checkout . to restore the working directory
 * before the exception propagates.
 */
class Issue107Test extends ComposerUpdateIntegrationBase
{
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.0.2';
    protected $composerAssetFiles = 'composer-psr-log';

    protected $checkedOutAfterCommitError = false;

    public function testCheckoutAfterCommitError()
    {
        self::assertFalse($this->checkedOutAfterCommitError);
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage('Error committing the composer files. They are probably not changed.', $this->cosy);
        self::assertTrue($this->checkedOutAfterCommitError);
    }

    protected function handleExecutorReturnCallback($cmd, &$return)
    {
        $command_string = implode(' ', $cmd);
        if (strpos($command_string, 'git commit composer.json composer.lock -m Update psr/log') === 0) {
            $return = 1;
        }
        // After a failed commit, we expect git checkout . to be called.
        if ($cmd === ['git', 'checkout', '.'] && $this->lastCommand && strpos(implode(' ', $this->lastCommand), 'git commit composer.json composer.lock -m Update psr/log') === 0) {
            $this->checkedOutAfterCommitError = true;
        }
    }
}
