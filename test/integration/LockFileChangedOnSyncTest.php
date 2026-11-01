<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test that we stop if the default branch changes the lock file mid-run.
 *
 * If for example the pull request for the update is merged after we have
 * installed and checked for updates, the lock file we get when syncing the
 * default branch no longer matches the installed packages. Updating from there
 * would compare against the wrong versions, and report the update as failed.
 */
class LockFileChangedOnSyncTest extends ComposerUpdateIntegrationBase
{
    protected ?string $composerAssetFiles = 'composer-psr-log';
    protected ?string $packageVersionForFromUpdateOutput = '1.0.0';
    protected ?string $packageVersionForToUpdateOutput = '1.0.2';
    protected ?string $packageForUpdateOutput = 'psr/log';

    protected bool $updateCommandRan = false;

    public function testStopsWhenLockFileChangesOnSync() : void
    {
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage('The lock file changed when syncing the default branch', $this->cosy);
        self::assertFalse($this->updateCommandRan);
        self::assertEmpty($this->prParamsArray);
        self::assertFalse($this->findMessage('was not updated', $this->cosy));
    }

    protected function handleExecutorReturnCallback(array $cmd, &$return) : void
    {
        if ($cmd == ['git', 'pull', '--unshallow']) {
            // Simulate the default branch having received the update since the
            // run started.
            $this->placeUpdatedComposerLock();
        }
        if ($cmd == $this->createExpectedCommandForPackage('psr/log')) {
            $this->updateCommandRan = true;
        }
    }
}
