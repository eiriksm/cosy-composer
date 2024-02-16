<?php

namespace eiriksm\CosyComposerTest\integration;

class NotUpdatedTest extends ComposerUpdateIntegrationBase
{
    protected $composerAssetFiles = 'composer-psr-log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.0.2';
    protected $packageForUpdateOutput = 'psr/log';

    public function testNotUpdatedInComposerLock()
    {
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage('psr/log was not updated running composer update', $this->cosy);
    }

    protected function placeUpdatedComposerLock()
    {
        $this->placeComposerLockContentsFromFixture(sprintf('%s.lock', $this->composerAssetFiles), $this->dir);
    }
}
