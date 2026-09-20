<?php

namespace eiriksm\CosyComposerTest\integration;

class NotUpdatedTest extends ComposerUpdateIntegrationBase
{
    protected ?string $composerAssetFiles = 'composer-psr-log';
    protected ?string $packageVersionForFromUpdateOutput = '1.0.0';
    protected ?string $packageVersionForToUpdateOutput = '1.0.2';
    protected ?string $packageForUpdateOutput = 'psr/log';

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
