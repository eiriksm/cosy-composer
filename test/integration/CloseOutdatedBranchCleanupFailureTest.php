<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test that a failed cleanup of a superseded branch is logged.
 */
class CloseOutdatedBranchCleanupFailureTest extends CloseOutdatedBranchCleanupTest
{
    protected bool $deleteBranchResult = false;

    protected function assertBranchCleanupLogged() : void
    {
        $this->assertOutputContainsMessage('Could not delete branch psrlog100111 after closing PR 125', $this->cosy);
        self::assertFalse($this->findMessage('Successfully deleted branch', $this->cosy));
    }
}
