<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test for consecutive runs to avoid errors with missing lock files.
 *
 * @see https://github.com/eiriksm/cosy-composer/issues/105
 * @see https://github.com/eiriksm/cosy-composer/issues/106
 */
class UpdateConsecutiveNoLockTest extends ComposerUpdateIntegrationBase
{
    protected $composerAssetFiles = 'composer.consecutive.nolock';

    protected $removedLockFile = false;

    protected $composerInstallCount = 0;

    public function setUp() : void
    {
        parent::setUp();
        $this->updateJson = '{"installed": [{"name": "psr/cache", "version": "1.0.0", "latest": "1.0.1", "latest-status": "semver-safe-update"},{"name": "psr/log", "version": "1.1.3", "latest": "1.1.4", "latest-status": "semver-safe-update"}]}';
    }

    public function testConsecutiveRunsWithNoLockFile() : void
    {
        $this->runtestExpectedOutput();
        // Both packages should be updated via composer require (since there's no lock file).
        $this->assertOutputContainsMessage('Creating pull request from psrcache100101', $this->cosy);
        $this->assertOutputContainsMessage('Creating pull request from psrlog11311', $this->cosy);
        // The lock file should be removed between consecutive runs since the
        // project originally had no lock file.
        self::assertTrue($this->removedLockFile, 'The lock file should be removed between consecutive runs when the project has no initial lock file');
        // Composer install should run multiple times: initial installs plus between updates.
        self::assertGreaterThanOrEqual(4, $this->composerInstallCount, 'Composer install should run between consecutive updates');
    }

    /**
     * @param array<string> $cmd
     */
    protected function handleExecutorReturnCallback(array $cmd, &$return) : void
    {
        // With no lock file, the code uses composer require with the constraint prefix.
        $require_commands = [
            ["composer", "require", '-n', '--no-ansi', 'psr/cache:~1.0.1', '--update-with-dependencies'],
            ["composer", "require", '-n', '--no-ansi', 'psr/log:~1.1.4', '--update-with-dependencies'],
        ];
        foreach ($require_commands as $expected_command) {
            if ($expected_command === $cmd) {
                $this->placeUpdatedComposerLock();
            }
        }
        if ($cmd === ['rm', 'composer.lock']) {
            $this->removedLockFile = true;
            // Actually remove the lock file to simulate the rm command.
            @unlink($this->dir . '/composer.lock');
        }
        if (count($cmd) >= 2 && $cmd[0] === 'composer' && $cmd[1] === 'install') {
            $this->composerInstallCount++;
            // Only place the lock file starting from the 3rd composer install
            // call. The first two happen before the initial lock file check
            // (lines 619 and 663 in CosyComposer.php). The 3rd one (line 688)
            // is after the check, simulating composer install creating the lock
            // file for the first time.
            if ($this->composerInstallCount >= 3) {
                $this->placeComposerLockContentsFromFixture('composer.consecutive.nolock.lock', $this->dir);
            }
        }
    }

    protected function placeInitialComposerLock() : void
    {
        // Deliberately do NOT place a lock file to simulate a project without one.
    }

    /**
     * @return array<string>
     */
    protected function getBranchesFlattened() : array
    {
        return [];
    }
}
