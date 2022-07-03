<?php

namespace eiriksm\CosyComposerTest\integration;

use Bitbucket\Api\Repositories;
use Bitbucket\Client;
use eiriksm\CosyComposer\CommandExecuter;
use eiriksm\CosyComposer\CosyComposer;
use eiriksm\CosyComposer\ProviderFactory;
use eiriksm\CosyComposer\Providers\Bitbucket;
use eiriksm\CosyComposer\Providers\Github;
use eiriksm\CosyComposerTest\integration\Base;
use eiriksm\CosyComposerTest\integration\ComposerUpdateIntegrationBase;
use Violinist\ComposerUpdater\Exception\NotUpdatedException;
use Violinist\Slug\Slug;

/**
 * Test that we are not closing PRs when things do not go as planned.
 */
class CloseOutdatedUpdateCrashedTest extends CloseOutdatedTest
{
    protected $expectedClosedPrs = [];

    protected function placeUpdatedComposerLock()
    {
        throw new NotUpdatedException('Not updated sorry');
    }
}
