<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test that we do not report a failed update if the lock file was already ahead.
 *
 * If the lock file on disk has another version than the one the update check
 * was based on, composer will report nothing as updated. That is not a failed
 * update, so we should not report it as one.
 */
class LockFileAheadOfUpdateDataTest extends ComposerUpdateIntegrationBase
{
    protected ?string $composerAssetFiles = 'composer-psr-log';
    protected ?string $packageVersionForFromUpdateOutput = '1.0.0';
    protected ?string $packageVersionForToUpdateOutput = '1.0.2';
    protected ?string $packageForUpdateOutput = 'psr/log';

    public function testLockFileAheadOfUpdateData() : void
    {
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage('Skipping psr/log since the lock file did not match the data the update check was based on: psr/log (expected 1.0.2 at 4ebe3a8bf773a19edfe0a84b6585ba3d401b724d, found 1.0.2 at changed)', $this->cosy);
        self::assertFalse($this->findMessage('was not updated', $this->cosy));
        self::assertEmpty($this->prParamsArray);
    }

    protected function handleExecutorReturnCallback(array $cmd, &$return) : void
    {
        if (array_slice($cmd, 0, 3) == ['git', 'checkout', '-b']) {
            // Simulate the lock file already having the update when we are
            // about to run it.
            $this->placeUpdatedComposerLock();
        }
    }
}
