<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Tests that a group rule config can set its own branch prefix.
 *
 * The composer.json fixture is identical to the base group fixture, except
 * each group rule sets a "branch_prefix": "group-prefix/" in its own "config".
 * So the branches created for the groups should be prefixed with that value,
 * instead of using the (empty) global branch prefix.
 */
class GroupBranchPrefixTest extends GroupsForDrupalCoreAndContribTest
{
    protected ?string $composerAssetFiles = 'composer-group-contrib-and-core-branch-prefix';

    protected function placeInitialComposerLock(): void
    {
        // The lock contents are identical to the base group fixture, so reuse
        // them instead of duplicating the (large) lock fixtures.
        $this->placeComposerLockContentsFromFixture('composer-group-contrib-and-core.lock', $this->dir);
    }

    public function testUpdatesGroupedContribAndCore() : void
    {
        $this->runtestExpectedOutput();
        // The branch names should carry the branch prefix defined in each group
        // rule config, on top of the slug based name.
        self::assertEquals('group-prefix/minor-and-patch-contrib', $this->prParamsArray[0]["head"]);
        self::assertEquals('group-prefix/minor-patch-core', $this->prParamsArray[1]["head"]);
    }
}
