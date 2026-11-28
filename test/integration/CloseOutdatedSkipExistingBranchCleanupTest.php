<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\Providers\NamedPrs;
use Violinist\Slug\Slug;

/**
 * Test branch cleanup and ownership checks when skipping an existing update.
 */
class CloseOutdatedSkipExistingBranchCleanupTest extends CloseOutdatedSkipExistingTest
{
    /** @var string[] */
    private array $deletedBranches = [];

    public function setUp() : void
    {
        parent::setUp();
        // PR 124 was opened by someone else, so it is left alone. PR 125 has no
        // known author, so it is closed, but its branch is not deleted.
        $this->expectedClosedPrs = [123, 125];
        $this->getMockProvider()->method('getAuthenticatedUsername')
            ->willReturn('violinist-bot');
        $this->getMockProvider()->method('deleteBranch')
            ->willReturnCallback(function (Slug $slug, string $branch_name) {
                $this->deletedBranches[] = $branch_name;
                return true;
            });
    }

    public function testOutdatedClosed() : void
    {
        parent::testOutdatedClosed();
        self::assertEquals(['psrlog100113'], $this->deletedBranches);
        $this->assertOutputContainsMessage('Not closing PR number 124, since it was not opened by us', $this->cosy);
        $this->assertOutputContainsMessage('Successfully deleted branch psrlog100113 after closing PR 123', $this->cosy);
    }

    protected function getPrsNamed() : NamedPrs
    {
        $named_prs = new NamedPrs();
        $fake_commit = 'test commit
------
update_data:
  package: psr/log';
        $pr_array = [
            'psrlog100114' => [
                'base' => [
                    'sha' => 123,
                ],
                'number' => 456,
                'title' => 'Update psr/log from 1.0.0 to 1.1.4',
                'user' => [
                    'login' => 'violinist-bot',
                ],
                'head' => [
                    'ref' => 'psrlog100114',
                ],
            ],
            'psrlog100113' => [
                'number' => 123,
                'title' => 'Test update',
                'user' => [
                    'login' => 'violinist-bot',
                ],
                'head' => [
                    'ref' => 'psrlog100113',
                ],
            ],
            'psrlog100112' => [
                'number' => 124,
                'title' => 'Test update',
                'user' => [
                    'login' => 'some-regular-human-user',
                ],
                'head' => [
                    'ref' => 'psrlog100112',
                ],
            ],
            'psrlog100111' => [
                'number' => 125,
                'title' => 'Test update',
                'head' => [
                    'ref' => 'psrlog100111',
                ],
            ],
        ];
        foreach ($pr_array as $value) {
            $named_prs->addFromCommit($fake_commit, $value);
        }
        return $named_prs;
    }
}
