<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\Providers\NamedPrs;

/**
 * Test that a no longer relevant PR opened by someone else is left alone.
 */
class CloseNoLongerRelevantDifferentAuthorTest extends ClosePrsNotTrulyOutdatedPackageTest
{
    public function setUp() : void
    {
        parent::setUp();
        $this->expectedClosedPrs = [];
        $this->getMockProvider()->method('getAuthenticatedUsername')
            ->willReturn('violinist-bot');
        $this->getMockProvider()->expects($this->never())
            ->method('deleteBranch');
    }

    public function testOutdatedClosed() : void
    {
        parent::testOutdatedClosed();
        $this->assertOutputContainsMessage('Not closing PR number 789, since it was not opened by us', $this->cosy);
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
                'login' => 'some-regular-human-user',
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
