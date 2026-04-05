<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\Providers\NamedPrs;

/**
 * Test that close-outdated does not close PRs for packages that are still
 * outdated but excluded by the allow_list config.
 *
 * Scenario: allow_list only includes psr/log, but psr/cache is also outdated
 * and has an existing PR. The PR for psr/cache should NOT be closed because
 * the package is still outdated — it's just not being managed by this config.
 */
class CloseOutdatedAllowListTest extends CloseOutdatedBase
{
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.1.3';
    protected $packageVersionForToUpdateOutput = '1.1.4';
    protected $composerAssetFiles = 'composer.close.outdated.allow_list';
    protected $checkPrUrl = true;
    // psr/cache PR should NOT be closed — it's still outdated, just not in
    // the allow_list.
    protected $expectedClosedPrs = [];

    public function setUp() : void
    {
        parent::setUp();
        // Both psr/log and psr/cache are outdated, but only psr/log is in the
        // allow_list (plus direct deps via always_allow_direct_dependencies).
        $this->updateJson = '{"installed": [' .
            '{"name": "psr/log", "version": "1.1.3", "latest": "1.1.4", "latest-status": "semver-safe-update"},' .
            '{"name": "psr/cache", "version": "1.0.0", "latest": "1.0.1", "latest-status": "semver-safe-update"}' .
        ']}';
    }

    protected function handleExecutorReturnCallback($cmd, &$return)
    {
        $expected_command = $this->createExpectedCommandForPackage('psr/log');
        if ($expected_command === $cmd) {
            $this->placeUpdatedComposerLock();
        }
    }

    protected function getPrsNamed() : NamedPrs
    {
        $named_prs = new NamedPrs();
        $fake_commit = 'test commit
------
update_data:
  package: psr/cache';
        $named_prs->addFromCommit($fake_commit, [
            'number' => 789,
            'title' => 'Update psr/cache from 1.0.0 to 1.0.1',
            'base' => [
                'ref' => 'master',
                'sha' => 123,
            ],
            'head' => [
                'ref' => 'psrcache100101',
            ],
        ]);
        return $named_prs;
    }
}
