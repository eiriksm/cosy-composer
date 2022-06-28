<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\ArrayOutput\ArrayOutput;

class UpdateConcurrentOutdatedBranchTest extends ComposerUpdateIntegrationBase
{
    protected $composerAssetFiles = 'composer.concurrent.update_branch';
    private $sha;

    public function setUp()
    {
        parent::setUp();
        $this->sha = 123;

        $mock_output = $this->createMock(ArrayOutput::class);
        $mock_output->method('fetch')
            ->willReturn([
                [
                    '{"installed": [{"name": "psr/cache", "version": "1.0.0", "latest": "1.0.1", "latest-status": "semver-safe-update"},{"name": "psr/log", "version": "1.1.3", "latest": "1.1.4", "latest-status": "semver-safe-update"}]}',
                ]
            ]);
        $this->cosy->setOutput($mock_output);
    }

    public function testUpdateConcurrentWithOutdatedBranch()
    {
        $this->sha = '456';
        $this->runtestExpectedOutput();
        // This means we expect the first package (psr/cache) to be updated, since the PR is out of date. This should
        // show in the messages then.
        $this->assertOutputContainsMessage('Creating pull request from psrcache100101', $this->cosy);
        $msg = $this->findMessage('Running composer update for package psr/log', $this->cosy);
        self::assertFalse($msg);
    }

    public function testUpdateConcurrentWithUpToDateBranch()
    {
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage('Skipping psr/cache because a pull request already exists', $this->cosy);
        $this->assertOutputContainsMessage('Skipping psr/log because the number of max concurrent PRs (1) seems to have been reached', $this->cosy);
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
