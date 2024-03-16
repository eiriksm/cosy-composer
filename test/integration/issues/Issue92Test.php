<?php

namespace eiriksm\CosyComposerTest\integration\issues;

use eiriksm\CosyComposerTest\integration\ComposerUpdateIntegrationBase;

/**
 * Class Issue92Test.
 *
 * Issue 92 was that after we switched the updater package, the output from the failed composer update command would not
 * get logged.
 */
class Issue92Test extends ComposerUpdateIntegrationBase
{
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.0.2';
    protected $composerAssetFiles = 'composer-psr-log';
    protected $called = false;
    protected $errorOutput = '';

    public function testIssue92()
    {
        $this->assertEquals(false, $this->called);
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage('Trying to update
Failed to update', $this->cosy);
        $this->assertOutputContainsMessage('psr/log was not updated running composer update', $this->cosy);
        $this->assertEquals(true, $this->called);
    }

    protected function handleExecutorReturnCallback($cmd, &$return)
    {
        $cmd_string = implode(' ', $cmd);
        $this->errorOutput = '';
        if ($cmd === $this->createExpectedCommandForPackage($this->packageForUpdateOutput)) {
            $this->errorOutput = "Trying to update\nFailed to update";
            // Also avoid placing the updated composer lock there.
            $this->placeInitialComposerLock();
        }
        if (strpos($cmd_string, 'rm -rf /tmp/') === 0) {
            $this->called = true;
        }
    }

    protected function processLastOutput(array &$output)
    {
        $output['stderr'] = $this->errorOutput;
    }
}
