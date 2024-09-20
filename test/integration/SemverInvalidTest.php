<?php

namespace eiriksm\CosyComposerTest\integration;

class SemverInvalidTest extends ComposerUpdateIntegrationBase
{
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '2.0.1';
    protected $packageForUpdateOutput = 'psr/log';
    protected $composerAssetFiles = 'composer-psr-log-with-extra-allow-beyond';

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
