<?php

namespace eiriksm\CosyComposerTest\integration\issues;

use eiriksm\CosyComposerTest\integration\ComposerUpdateIntegrationBase;

/**
 * Test for issue 211.
 */
class Issue211Test extends ComposerUpdateIntegrationBase
{
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.1.3';
    protected $composerAssetFiles = 'composer-no-lock';
    protected $checkPrUrl = true;

    public function testLockDataNotFailed()
    {
        $this->runtestExpectedOutput();
    }

    protected function createExpectedCommandForPackage($package)
    {
        return "composer require --dev -n --no-ansi $package:1.1.3 --update-with-dependencies ";
    }

    protected function handleExecutorReturnCallback($cmd, &$return)
    {

        if ($cmd === 'composer install --no-ansi -n') {
            $this->placeComposerLockContentsFromFixture('composer164.lock', $this->dir);
        }
    }
}
