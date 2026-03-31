<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\Providers\NamedPrs;

/**
 * Test that PRs are closed when a package appears in composer outdated but is
 * not truly outdated (e.g. abandoned with latest-status "up-to-date").
 *
 * Scenario: psr/cache shows up in `composer outdated` output but with
 * latest-status "up-to-date" (or missing latest/latest-status). The cleanup
 * phase in CosyComposer::run() removes these entries from $data before
 * $all_outdated_package_names is built, so psr/cache is correctly excluded
 * from the outdated list. closePrsForNoLongerRelevantPackages() then sees
 * psr/cache is no longer outdated and closes its PR.
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
        // removes it from $data before building $all_outdated_package_names.
        // The PR for psr/cache should therefore be closed.
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
