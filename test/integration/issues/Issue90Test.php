<?php

namespace eiriksm\CosyComposerTest\integration\issues;

use eiriksm\CosyComposerTest\GetCosyTrait;
use eiriksm\CosyComposerTest\integration\ComposerUpdateIntegrationBase;

/**
 * Class Issue90Test.
 *
 * Issue 90 was the fact that after we switched the updating to the updater package, the changelogs might be empty,
 * since we did not read the "after-lock-data" in the runner class.
 */
class Issue90Test extends ComposerUpdateIntegrationBase
{
    use GetCosyTrait;

    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.0.2';
    protected $composerAssetFiles = 'composer-psr-log';
    protected $called = false;

    public function testChangelogCalledWithReference()
    {
        self::assertEquals(false, $this->called);
        $this->runtestExpectedOutput();
        $this->assertEquals(true, $this->called);
    }

    protected function handleExecutorReturnCallback($cmd, &$return)
    {
        if ($cmd === ["git", '-C', '/tmp/e9a8b66d7a4bac57a08b8f0f2664c50f', 'log', '4ebe3a8bf773a19edfe0a84b6585ba3d401b724d..changed', "--oneline"]) {
            $this->called = true;
        }
    }
}
