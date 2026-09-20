<?php

namespace eiriksm\CosyComposerTest\integration;

class ComposerInstallTest extends ComposerUpdateIntegrationBase
{
    protected ?string $packageVersionForFromUpdateOutput = '1.0.0';
    protected ?string $packageVersionForToUpdateOutput = '1.0.1';
    protected ?string $packageForUpdateOutput = 'psr/log';
    protected ?string $composerAssetFiles = 'composer-psr-log-with-no-scripts';
    protected bool $noScriptPassed = false;

    public function testNoScriptsPassed() : void
    {
        $this->runtestExpectedOutput();
        self::assertTrue($this->noScriptPassed);
    }

    protected function placeInitialComposerLock() : void
    {
        $this->placeComposerLockContentsFromFixture('composer-psr-log.lock', $this->dir);
    }

    protected function placeUpdatedComposerLock() : void
    {
        $this->placeComposerLockContentsFromFixture('composer-psr-log.lock.updated', $this->dir);
    }

    protected function handleExecutorReturnCallback(array $cmd, &$return) : void
    {
        $asString = implode(' ', $cmd);
        if (str_starts_with($asString, 'composer install')) {
            $this->noScriptPassed = in_array('--no-scripts', $cmd);
        }
        parent::handleExecutorReturnCallback($cmd, $return);
    }
}
