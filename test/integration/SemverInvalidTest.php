<?php

namespace eiriksm\CosyComposerTest\integration;

class SemverInvalidTest extends ComposerUpdateIntegrationBase
{
    protected ?string $packageVersionForFromUpdateOutput = '1.0.0';
    protected ?string $packageVersionForToUpdateOutput = '2.0.1';
    protected ?string $packageForUpdateOutput = 'psr/log';
    protected ?string $composerAssetFiles = 'composer-psr-log-with-extra-allow-beyond';

    public function testUpdatesFoundButNotSemverValid()
    {
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage('Package psr/log with the constraint ^1.0 can not be updated to 2.0.1.', $this->cosy);
    }

    protected function placeInitialComposerLock()
    {
        $this->placeComposerLockContentsFromFixture('composer-psr-log.lock', $this->dir);
    }

    protected function placeUpdatedComposerLock()
    {
        // Empty on purpose.
    }
}
