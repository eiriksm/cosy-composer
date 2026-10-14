<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\Providers\NamedPrs;
use Violinist\Slug\Slug;

/**
 * Test branch cleanup when an open Violinist PR is no longer relevant.
 */
class CloseNoLongerRelevantBranchCleanupTest extends ClosePrsNotTrulyOutdatedPackageTest
{
    private $deletedBranches = [];

    public function setUp() : void
    {
        parent::setUp();
        $this->getMockProvider()->method('getAuthenticatedUsername')
            ->willReturn('violinist-bot');
        $this->getMockProvider()->method('deleteBranch')
            ->willReturnCallback(function (Slug $slug, string $branch_name) {
                $this->deletedBranches[] = $branch_name;
            });
    }

    public function testOutdatedClosed()
    {
        parent::testOutdatedClosed();
        self::assertEquals(['psrcache100101'], $this->deletedBranches);
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
            'user' => [
                'login' => 'violinist-bot',
            ],
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
