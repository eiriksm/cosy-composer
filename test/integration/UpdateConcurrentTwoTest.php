<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\PrParamsCreator;
use eiriksm\CosyComposer\Providers\NamedPrs;
use eiriksm\ViolinistMessages\ViolinistMessages;
use Violinist\SymfonyCloudSecurityChecker\SecurityChecker;

class UpdateConcurrentTwoTest extends ComposerUpdateIntegrationBase
{
    protected $sha;
    protected ?string $composerAssetFiles = 'composer.concurrent.two';
    protected string $psrCachePrTitle = 'Update psr/cache from 1.0.0 to 1.0.1';

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

        // psr/cache also matches the wildcard, so its existing PR does not
        // eat into the budget either. The limit is therefore never actually
        // reached, so psr/log is processed without needing to log a bypass
        // message for it.
        self::assertFalse($this->findMessage('seems to have been reached', $this->cosy));
        self::assertFalse($this->findMessage('is configured to bypass the concurrent limit', $this->cosy));
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
        // psr/cache is a bypass package, so its existing PR does not eat into the
        // concurrent limit budget. psr/log should therefore be processed instead
        // of throttled.
        self::assertFalse($this->findMessage(
            'Skipping psr/log because the number of max concurrent PRs',
            $this->cosy
        ));
        $this->assertOutputContainsMessage('Running composer update for package psr/log', $this->cosy);
    }

    public function testBypassedPackageDoesNotConsumeConcurrentLimitBudget(): void
    {
        $this->sha = 456;
        $this->setConcurrentUpdatesBypassPackages(['psr/cache']);
        $this->runtestExpectedOutput();

        // psr/cache's PR is outdated, so it gets recreated, which would normally
        // count towards the concurrent limit budget.
        $this->assertOutputContainsMessage('Creating pull request from psrcache100101', $this->cosy);
        // Since psr/cache is a bypass package, that PR creation should not count
        // towards the limit of 1, leaving the slot free for psr/log.
        self::assertFalse($this->findMessage(
            'Skipping psr/log because the number of max concurrent PRs',
            $this->cosy
        ));
        $this->assertOutputContainsMessage('Running composer update for package psr/log', $this->cosy);
    }

    public function testSecurityBypassDoesNotCreateDuplicateForExistingPullRequest(): void
    {
        // psr/cache already has an up-to-date PR (default sha matches), which
        // is skipped before IndividualUpdater even runs. Flagging it as a
        // security update should exempt that skip from the budget too.
        $this->setConcurrentLimitWithSecurityBypass();
        $this->markPackageAsSecurityUpdate('psr/cache');
        $this->runtestExpectedOutput();

        $this->assertOutputContainsMessage('Skipping psr/cache because a pull request already exists', $this->cosy);
        self::assertFalse($this->findMessage('seems to have been reached', $this->cosy));
        $this->assertOutputContainsMessage('Running composer update for package psr/log', $this->cosy);
    }

    public function testSecurityBypassDoesNotConsumeConcurrentLimitBudget(): void
    {
        // psr/cache's PR is outdated, so it gets recreated, which would
        // normally count towards the concurrent limit budget.
        $this->sha = 456;
        $this->setConcurrentLimitWithSecurityBypass();
        $this->markPackageAsSecurityUpdate('psr/cache');
        $this->runtestExpectedOutput();

        $this->assertOutputContainsMessage('Creating pull request from psrcache100101', $this->cosy);
        // Since psr/cache is allowed through as a security update, that PR
        // creation should not count towards the limit of 1, leaving the slot
        // free for psr/log.
        self::assertFalse($this->findMessage('seems to have been reached', $this->cosy));
        $this->assertOutputContainsMessage('Running composer update for package psr/log', $this->cosy);
    }

    public function testBypassConfigIsNoOpWhenConcurrentLimitIsUnset(): void
    {
        // With no concurrent limit set, the throttling block (and therefore
        // the bypass check) is skipped entirely, so configuring a bypass
        // package should have no effect on the output.
        $this->setConcurrentUpdatesBypassPackagesWithoutLimit(['psr/*']);
        $this->runtestExpectedOutput();

        self::assertFalse($this->findMessage('is configured to bypass the concurrent limit', $this->cosy));
        self::assertFalse($this->findMessage('seems to have been reached', $this->cosy));
        $this->assertOutputContainsMessage('Skipping psr/cache because a pull request already exists', $this->cosy);
        $this->assertOutputContainsMessage('Running composer update for package psr/log', $this->cosy);
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
                'title' => $this->psrCachePrTitle,
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

    /**
     * @param array<int, string> $packages
     */
    protected function setConcurrentUpdatesBypassPackages(array $packages): void
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

    /**
     * @param array<int, string> $packages
     */
    protected function setConcurrentUpdatesBypassPackagesWithoutLimit(array $packages): void
    {
        $composer_file = sprintf('%s/composer.json', $this->dir);
        $composer_data = json_decode(file_get_contents($composer_file));
        $composer_data->extra->violinist->number_of_concurrent_updates = 0;
        $composer_data->extra->violinist->concurrent_updates_bypass_packages = $packages;
        file_put_contents(
            $composer_file,
            json_encode($composer_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    protected function setConcurrentLimitWithSecurityBypass(): void
    {
        $composer_file = sprintf('%s/composer.json', $this->dir);
        $composer_data = json_decode(file_get_contents($composer_file));
        $composer_data->extra->violinist->number_of_concurrent_updates = 1;
        $composer_data->extra->violinist->allow_security_updates_on_concurrent_limit = true;
        file_put_contents(
            $composer_file,
            json_encode($composer_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    protected function markPackageAsSecurityUpdate(string $package_name): void
    {
        $checker = $this->createMock(SecurityChecker::class);
        $checker->method('checkDirectory')->willReturn([
            $package_name => true,
        ]);
        $this->cosy->getCheckerFactory()->setChecker($checker);
        if ($package_name === 'psr/cache') {
            // The existing PR's title has to match what a security update
            // would compute (including the "[SECURITY] " prefix, which
            // actually contains a non-breaking space), otherwise the
            // pre-filter treats it as needing a title update instead of an
            // already-up-to-date PR. Compute it the same way production
            // does, rather than hardcoding the exact bytes here.
            $pr_params_creator = new PrParamsCreator(new ViolinistMessages());
            $fake_item = (object) ['name' => 'psr/cache', 'version' => '1.0.0', 'latest' => '1.0.1'];
            $fake_post_update = (object) ['version' => '1.0.1'];
            $this->psrCachePrTitle = $pr_params_creator->createTitle($fake_item, $fake_post_update, true);
        }
    }
}
