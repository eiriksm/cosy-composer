<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\Providers\NamedPrs;

/**
 * Test that PRs are closed when a package appears in composer outdated but is
 * not truly outdated (e.g. abandoned with latest-status "up-to-date").
 *
 * Scenario: psr/cache shows up in `composer outdated` output but with
 * latest-status "up-to-date" (or missing latest/latest-status). The cleanup
 * phase in CosyComposer::run() that unsets entries missing latest/latest-status
 * or marked up-to-date removes these entries from $data. However,
 * $all_outdated_package_names was already built from the raw $data before that
 * cleanup. This means psr/cache is still in $all_outdated_package_names, so
 * closePrsForNoLongerRelevantPackages() skips it and the PR is NOT closed -
 * even though the package is not truly outdated.
 *
 * This test verifies whether this bug exists: expectedClosedPrs is [789],
 * meaning we WANT the PR to be closed. If the test fails, the bug is confirmed.
 */
class ClosePrsNotTrulyOutdatedPackageTest extends CloseOutdatedBase
{
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.1.3';
    protected $packageVersionForToUpdateOutput = '1.1.4';
    protected $composerAssetFiles = 'composer.close.updated_package';
    protected $checkPrUrl = true;
    protected $expectedClosedPrs = [789];

    public function setUp() : void
    {
        parent::setUp();
        // psr/log is truly outdated (has latest + latest-status).
        // psr/cache appears in composer outdated but is abandoned / not truly
        // outdated: it has latest-status "up-to-date" so CosyComposer::run()
        // will unset it from $data. But because $all_outdated_package_names is
        // built before that cleanup, psr/cache remains in the list, preventing
        // closePrsForNoLongerRelevantPackages() from closing its PR.
        $this->updateJson = '{"installed": [' .
            '{"name": "psr/log", "version": "1.1.3", "latest": "1.1.4", "latest-status": "semver-safe-update"},' .
            '{"name": "psr/cache", "version": "1.0.0", "latest": "1.0.0", "latest-status": "up-to-date"}' .
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
