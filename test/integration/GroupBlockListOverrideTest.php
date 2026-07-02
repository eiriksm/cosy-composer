<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Tests that a group rule config can override the (global) block list.
 *
 * The composer.json fixture freezes ALL drupal packages with a top level
 * "blocklist": ["drupal/*"], but each group rule un-blocks the packages it
 * matches again through its own "config": { "blocklist": [] }. So the grouped
 * packages should still be updated, while the freeze would otherwise have
 * skipped every single one of them.
 */
class GroupBlockListOverrideTest extends GroupsForDrupalCoreAndContribTest
{
    protected $composerAssetFiles = 'composer-group-contrib-and-core-blocklist-override';

    protected function placeInitialComposerLock()
    {
        // The lock contents are identical to the base group fixture, so reuse
        // them instead of duplicating the (large) lock fixtures.
        $this->placeComposerLockContentsFromFixture('composer-group-contrib-and-core.lock', $this->dir);
    }
}
