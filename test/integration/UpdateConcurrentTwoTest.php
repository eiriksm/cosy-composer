<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\Providers\NamedPrs;

class UpdateConcurrentTwoTest extends ComposerUpdateIntegrationBase
{
    protected $sha;
    protected ?string $composerAssetFiles = 'composer.concurrent.two';

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

    public function testUpdateBypassesConcurrentLimitForExactPackage(): void
    {
        $this->setConcurrentUpdatesBypassPackages(['psr/log']);
        $this->runtestExpectedOutput();

        $this->assertOutputContainsMessage(
            'The concurrent limit (1) is reached, but psr/log is configured to bypass the concurrent limit, so we will try to update it anyway.',
            $this->cosy
        );
        $this->assertOutputContainsMessage('Running composer update for package psr/log', $this->cosy);
    }

    public function testUpdateBypassesConcurrentLimitForWildcardPackage(): void
    {
        $this->setConcurrentUpdatesBypassPackages(['psr/*']);
        $this->runtestExpectedOutput();

        $this->assertOutputContainsMessage(
            'The concurrent limit (1) is reached, but psr/log is configured to bypass the concurrent limit, so we will try to update it anyway.',
            $this->cosy
        );
        $this->assertOutputContainsMessage('Running composer update for package psr/log', $this->cosy);
    }

    public function testUpdateDoesNotBypassConcurrentLimitForNonMatchingPackage(): void
    {
        $this->setConcurrentUpdatesBypassPackages(['vendor/*']);
        $this->runtestExpectedOutput();

        $this->assertOutputContainsMessage(
            'Skipping psr/log because the number of max concurrent PRs (1) seems to have been reached',
            $this->cosy
        );
    }

    public function testBypassDoesNotCreateDuplicateForExistingPullRequest(): void
    {
        $this->setConcurrentUpdatesBypassPackages(['psr/cache']);
        $this->runtestExpectedOutput();

        $this->assertOutputContainsMessage('Skipping psr/cache because a pull request already exists', $this->cosy);
        $this->assertOutputContainsMessage(
            'Skipping psr/log because the number of max concurrent PRs (1) seems to have been reached',
            $this->cosy
        );
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

    protected function getPrsNamed() : NamedPrs
    {
        return NamedPrs::createFromArray([
            'psrcache100101' => [
                'base' => [
                    'sha' => $this->sha,
                ],
                'number' => 123,
                'title' => 'Update psr/cache from 1.0.0 to 1.0.1',
                'head' => [
                    'ref' => 'psrcache100101',
                ],
            ],
        ]);
    }

    protected function getBranchesFlattened()
    {
        return [
            'psrcache100101',
        ];
    }

    private function setConcurrentUpdatesBypassPackages(array $packages): void
    {
        $composer_file = sprintf('%s/composer.json', $this->dir);
        $composer_data = json_decode(file_get_contents($composer_file));
        $composer_data->extra->violinist->number_of_concurrent_updates = 1;
        $composer_data->extra->violinist->concurrent_updates_bypass_packages = $packages;
        file_put_contents(
            $composer_file,
            json_encode($composer_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }
}
