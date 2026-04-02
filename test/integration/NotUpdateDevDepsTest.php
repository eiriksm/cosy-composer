<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test for not updating dev deps when update_dev_dependencies is set to 0.
 *
 * @see https://github.com/eiriksm/cosy-composer/issues/113
 */
class NotUpdateDevDepsTest extends ComposerUpdateIntegrationBase
{
    protected $composerAssetFiles = 'composer-not-update-dev-deps';
    protected $checkPrUrl = true;

    public function setUp() : void
    {
        parent::setUp();
        $this->updateJson = '{"installed": [{"name": "psr/cache", "version": "1.0.0", "latest": "1.0.1", "latest-status": "semver-safe-update"},{"name": "psr/log", "version": "1.1.3", "latest": "1.1.4", "latest-status": "semver-safe-update"}]}';
    }

    public function testDevDepsNotUpdated()
    {
        $this->runtestExpectedOutput();
        // The non-dev dependency (psr/cache) should get a PR.
        $this->assertOutputContainsMessage('Creating pull request from psrcache100101', $this->cosy);
        // The dev dependency (psr/log) should be removed from updates.
        $this->assertOutputContainsMessage('Removing dev dependencies from updates since the option update_dev_dependencies is disabled', $this->cosy);
        // No update should be attempted for psr/log.
        $msg = $this->findMessage('Running composer update for package psr/log', $this->cosy);
        self::assertFalse($msg);
    }

    protected function handleExecutorReturnCallback($cmd, &$return)
    {
        $expected_command = $this->createExpectedCommandForPackage('psr/cache');
        if ($expected_command === $cmd) {
            $this->placeUpdatedComposerLock();
        }
    }
}
