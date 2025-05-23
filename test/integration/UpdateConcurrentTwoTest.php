<?php

namespace eiriksm\CosyComposerTest\integration;

class UpdateConcurrentTwoTest extends ComposerUpdateIntegrationBase
{
    protected $sha;
    protected $composerAssetFiles = 'composer.concurrent.two';

    public function setUp() : void
    {
        parent::setUp();
        $this->sha = 123;

        $this->updateJson = '{"installed": [{"name": "psr/cache", "version": "1.0.0", "latest": "1.0.1", "latest-status": "semver-safe-update"},{"name": "psr/log", "version": "1.1.3", "latest": "1.1.4", "latest-status": "semver-safe-update"}]}';
    }

    public function testUpdateConcurrentWithOutdatedBranch()
    {
        $this->sha = 456;
        $this->runtestExpectedOutput();
        // This means we expect the first package (psr/cache) to be updated, since the PR is out of date. This should
        // show in the messages then.
        $this->assertOutputContainsMessage('Creating pull request from psrcache100101', $this->cosy);
        // Plus, since the max is 2, the second package should also be updated.
        $output = $this->cosy->getOutput();
        $msg = $this->findMessage('Running composer update for package psr/log', $this->cosy);
        self::assertNotFalse($msg);
    }

    public function testUpdateConcurrentWithUpToDateBranch()
    {
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage('Skipping psr/cache because a pull request already exists', $this->cosy);
        // We only have one PR open. Our limit is 2.
        $msg = $this->findMessage('Skipping psr/log because the number of max concurrent PRs (2) seems to have been reached', $this->cosy);
        self::assertFalse($msg);
    }

    protected function handleExecutorReturnCallback($cmd, &$return)
    {
        $packages = [
            'psr/log',
            'psr/cache',
        ];
        foreach ($packages as $package) {
            $expected_command = $this->createExpectedCommandForPackage($package);
            if ($expected_command === $cmd) {
                $this->placeUpdatedComposerLock();
            }
        }
    }

    protected function getPrsNamed()
    {
        return [
            'psrcache100101' => [
                'base' => [
                    'sha' => $this->sha,
                ],
                'number' => 123,
                'title' => 'Update psr/cache from 1.0.0 to 1.0.1',
            ],
        ];
    }

    protected function getBranchesFlattened()
    {
        return [
            'psrcache100101',
        ];
    }
}
