<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\Providers\NamedPrs;

/**
 * Test that all violinist PRs are closed when no updates are available.
 */
class CloseAllPrsNoUpdatesTest extends CloseOutdatedBase
{
    protected $composerAssetFiles = 'composer.close.outdated';
    protected $checkPrUrl = false;
    protected $expectedClosedPrs = [101, 102];

    public function setUp() : void
    {
        parent::setUp();
        $this->updateJson = '{"installed": []}';
    }

    protected function getPrsNamed() : NamedPrs
    {
        $named_prs = new NamedPrs();
        $fake_commit_1 = 'test commit
------
update_data:
  package: psr/log';
        $named_prs->addFromCommit($fake_commit_1, [
            'number' => 101,
            'title' => 'Update psr/log from 1.0.0 to 1.0.1',
            'base' => [
                'ref' => 'master',
                'sha' => 123,
            ],
            'head' => [
                'ref' => 'psrlog100101',
            ],
        ]);
        $fake_commit_2 = 'test commit
------
update_data:
  package: psr/log';
        $named_prs->addFromCommit($fake_commit_2, [
            'number' => 102,
            'title' => 'Update psr/log from 1.0.0 to 1.1.0',
            'base' => [
                'ref' => 'master',
                'sha' => 123,
            ],
            'head' => [
                'ref' => 'psrlog100110',
            ],
        ]);
        return $named_prs;
    }
}
