<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test that an update ending up on a lower version is still reported.
 */
class NotUpdatedLowerVersionTest extends ComposerUpdateIntegrationBase
{
    protected ?string $composerAssetFiles = 'composer-psr-log';
    protected ?string $packageVersionForFromUpdateOutput = '1.0.0';
    protected ?string $packageVersionForToUpdateOutput = '1.0.2';
    protected ?string $packageForUpdateOutput = 'psr/log';

    public function testLowerVersionIsReportedAsNotUpdated() : void
    {
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage('psr/log was not updated running composer update', $this->cosy);
        self::assertFalse($this->findMessage('since the lock file did not match', $this->cosy));
    }

    protected function placeUpdatedComposerLock() : void
    {
        $lock = json_decode((string) file_get_contents(__DIR__ . '/../fixtures/composer-psr-log.lock'));
        $lock->packages[0]->version = '0.9.0';
        $lock->packages[0]->source->reference = 'lower';
        file_put_contents($this->dir . '/composer.lock', json_encode($lock));
    }
}
