<?php

namespace eiriksm\CosyComposerTest\integration;

use Violinist\ComposerUpdater\Exception\NotUpdatedException;

/**
 * Test that we are not closing PRs when things do not go as planned.
 */
class CloseOutdatedUpdateCrashedTest extends CloseOutdatedTest
{
    protected $checkPrUrl = false;
    protected $expectedClosedPrs = [];

    protected function placeUpdatedComposerLock()
    {
        throw new NotUpdatedException('Not updated sorry');
    }
}
