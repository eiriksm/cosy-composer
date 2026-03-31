<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\Providers\NamedPrs;

/**
 * Test that PRs are closed when the package has been updated outside of violinist.
 *
 * Scenario: psr/cache has a violinist PR, but someone updates psr/cache manually.
 * composer outdated no longer lists psr/cache. The PR should be closed.
 */
class ClosePrsUpdatedPackageTest extends CloseOutdatedBase
{
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.1.3';
    protected $packageVersionForToUpdateOutput = '1.1.4';
    protected $composerAssetFiles = 'composer.close.updated_package';
    protected $checkPrUrl = true;
    protected $expectedClosedPrs = [789];

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
