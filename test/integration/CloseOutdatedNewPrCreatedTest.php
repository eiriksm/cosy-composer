<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\Providers\NamedPrs;

/**
 * Test that when a new PR is created, old PRs for previous versions are closed.
 *
 * Scenario: A PR is open for psr/log 1.0.0 -> 1.0.1 and then an update run
 * creates a PR for 1.0.0 -> 1.1.4. The first PR should be closed.
 *
 * The old PR is found via commit metadata (addFromCommit), which is the
 * production code path.
 */
class CloseOutdatedNewPrCreatedTest extends CloseOutdatedBase
{
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.1.4';
    protected $composerAssetFiles = 'composer.close.outdated';
    protected $checkPrUrl = true;
    protected $expectedClosedPrs = [789];

    protected function getPrsNamed() : NamedPrs
    {
        $named_prs = new NamedPrs();
        $fake_commit = 'test commit
------
update_data:
  package: psr/log';
        $pr_array = [
            [
                'number' => 789,
                'title' => 'Update psr/log from 1.0.0 to 1.0.1',
                'base' => [
                    'ref' => 'master',
                    'sha' => 123,
                ],
                'head' => [
                    'ref' => 'psrlog100101',
                ],
            ],
        ];
        foreach ($pr_array as $value) {
            $named_prs->addFromCommit($fake_commit, $value);
        }
        return $named_prs;
    }
}
