<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test that a failed cleanup of a no longer relevant branch is logged.
 */
class CloseNoLongerRelevantBranchCleanupFailureTest extends CloseNoLongerRelevantBranchCleanupTest
{
    protected bool $deleteBranchResult = false;

    protected function assertBranchCleanupLogged() : void
    {
        $this->assertOutputContainsMessage('Could not delete branch psrcache100101 after closing PR 789', $this->cosy);
        self::assertFalse($this->findMessage('Successfully deleted branch', $this->cosy));
    }
}
