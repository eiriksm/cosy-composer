<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\Providers\NamedPrs;

/**
 * Test that PRs updating dependencies of a direct package are NOT closed.
 *
 * Scenario: allow_update_indirect_with_direct is enabled. psr/cache is an
 * indirect dependency of psr/log that is still outdated. A PR exists for
 * psr/log (updating its dependencies). The PR should NOT be closed, since
 * psr/cache traces back to psr/log.
 */
class ClosePrsIndirectWithDirectStillRelevantTest extends CloseOutdatedBase
{
    protected $composerAssetFiles = 'composer.close.indirect_with_direct';
    protected $checkPrUrl = false;
    protected $expectedClosedPrs = [];

    public function setUp() : void
    {
        parent::setUp();
        $this->updateJson = '{"installed": [{"name": "psr/cache", "version": "1.0.0", "latest": "1.0.1", "latest-status": "semver-safe-update"}]}';
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
  package: psr/log';
        $named_prs->addFromCommit($fake_commit, [
            'number' => 789,
            'title' => 'Update dependencies of psr/log',
            'base' => [
                'ref' => 'master',
                'sha' => 123,
            ],
            'head' => [
                'ref' => 'psrlog-deps',
            ],
        ]);
        return $named_prs;
    }
}
