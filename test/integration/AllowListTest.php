<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test for a default commit message.
 */
class AllowListTest extends ComposerUpdateIntegrationBase
{
    protected $composerAssetFiles = 'composer.allow';
    protected $hasUpdatedPsrLog = false;
    protected $hasUpdatedPsrCache = false;
    protected $packageForUpdateOutput = 'psr/cache';

    public function testAllowList()
    {
        $this->runtestExpectedOutput();
        self::assertEquals($this->hasUpdatedPsrLog, false);
        self::assertEquals($this->hasUpdatedPsrCache, true);
    }

    protected function createUpdateJsonFromData($package, $version, $new_version)
    {
        return '{"installed": [{"name": "psr/log", "version": "1.1.4", "latest": "1.1.0", "latest-status": "semver-safe-update"},{"name": "psr/cache", "version": "1.0.0", "latest": "1.0.1", "latest-status": "semver-safe-update"}]}';
    }

    protected function handleExecutorReturnCallback($cmd, &$return)
    {

        if (strpos($cmd, 'psr/log') !== false) {
            $this->hasUpdatedPsrLog = true;
        }
        if (strpos($cmd, 'psr/cache') !== false) {
            $this->hasUpdatedPsrCache = true;
        }
    }
}
