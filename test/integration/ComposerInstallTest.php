<?php

namespace eiriksm\CosyComposerTest\integration;

class ComposerInstallTest extends ComposerUpdateIntegrationBase
{
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.0.1';
    protected $packageForUpdateOutput = 'psr/log';
    protected $composerAssetFiles = 'composer-psr-log-with-no-scripts';
    protected $noScriptPassed = false;

    public function testNoScriptsPassed()
    {
        $this->runtestExpectedOutput();
        self::assertTrue($this->noScriptPassed);
    }

    protected function placeInitialComposerLock()
    {
        $this->placeComposerLockContentsFromFixture('composer-psr-log.lock', $this->dir);
    }

    protected function placeUpdatedComposerLock()
    {
        $this->placeComposerLockContentsFromFixture('composer-psr-log.lock.updated', $this->dir);
    }

    protected function handleExecutorReturnCallback(array $cmd, &$return)
    {
        $asString = implode(' ', $cmd);
        if (str_starts_with($asString, 'composer install')) {
            $this->noScriptPassed = in_array('--no-scripts', $cmd);
        }
        parent::handleExecutorReturnCallback($cmd, $return);
    }
}
