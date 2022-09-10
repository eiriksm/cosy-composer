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
use Violinist\Slug\Slug;

/**
 * Test that we are closing PRs not the latest and greatest.
 */
abstract class CloseOutdatedBase extends ComposerUpdateIntegrationBase
{
    protected $closedPrs = [];
    protected $expectedClosedPrs = [];

    public function setUp() : void
    {
        parent::setUp();
        $this->getMockProvider()
            ->method('closePullRequestWithComment')
            ->willReturnCallback(function (Slug $slug, $pr_id, $comment) {
                $this->closedPrs[] = $pr_id;
            });
    }

    public function testOutdatedClosed()
    {
        $this->runtestExpectedOutput();
        self::assertEquals($this->expectedClosedPrs, $this->closedPrs);
    }
}
