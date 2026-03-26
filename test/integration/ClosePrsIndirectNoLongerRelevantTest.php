<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\Providers\NamedPrs;

/**
 * Test that PRs are closed when an indirect package is no longer outdated.
 *
 * Scenario: check_only_direct_dependencies is disabled. psr/cache is an
 * indirect dependency that is no longer outdated (does not appear in composer
 * outdated). A violinist PR exists for psr/cache. The PR should be closed.
 */
class ClosePrsIndirectNoLongerRelevantTest extends CloseOutdatedBase
{
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.1.3';
    protected $packageVersionForToUpdateOutput = '1.1.4';
    protected $composerAssetFiles = 'composer.close.indirect_still_relevant';
    protected $checkPrUrl = true;
    protected $expectedClosedPrs = [789];

    public function setUp() : void
    {
        parent::setUp();
        // Only psr/log is outdated; psr/cache is NOT outdated anymore.
        $this->updateJson = '{"installed": [{"name": "psr/log", "version": "1.1.3", "latest": "1.1.4", "latest-status": "semver-safe-update"}]}';
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
