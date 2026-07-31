<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Tests that a group rule can set its own commit message convention.
 *
 * The composer.json fixture sets "commit_message_convention": "conventional"
 * on the "Minor and Patch Contrib" group rule only. So the contrib group commit
 * should use the conventional format ("build(deps): ..."), while the core group,
 * which does not set a convention, should keep the default format.
 */
class GroupCommitMessageConventionTest extends GroupsForDrupalCoreAndContribTest
{
    protected ?string $composerAssetFiles = 'composer-group-commit-convention';

    private bool $foundConventionalContribCommit = false;
    private bool $foundDefaultCoreCommit = false;

    protected function placeInitialComposerLock(): void
    {
        // The lock contents are identical to the base group fixture, so reuse
        // them instead of duplicating the (large) lock fixtures.
        $this->placeComposerLockContentsFromFixture('composer-group-contrib-and-core.lock', $this->dir);
    }

    public function testUpdatesGroupedContribAndCore() : void
    {
        // The parent test asserts the default (non-conventional) contrib commit
        // message, but this fixture opts the contrib group rule into the
        // conventional convention. So assert the per-group behaviour instead.
        $this->runtestExpectedOutput();
        self::assertTrue($this->foundConventionalContribCommit, 'The contrib group commit used the conventional convention from its group rule config');
        self::assertTrue($this->foundDefaultCoreCommit, 'The core group commit used the default convention, since its group rule did not set one');
    }

    /**
     * @param string[] $cmd
     * @param mixed $return
     */
    public function handleExecutorReturnCallback(array $cmd, &$return) : void
    {
        parent::handleExecutorReturnCallback($cmd, $return);
        if (in_array('build(deps): Update dependency group Minor and Patch Contrib', $cmd, true)) {
            $this->foundConventionalContribCommit = true;
        }
        if (in_array('Update dependency group Minor and Patch Core', $cmd, true)) {
            $this->foundDefaultCoreCommit = true;
        }
    }
}
