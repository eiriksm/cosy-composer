<?php

namespace eiriksm\CosyComposerTest\integration;

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
        putenv('USE_CLOSE_NO_LONGER_RELEVANT=true');
        $this->getMockProvider()
            ->method('closePullRequestWithComment')
            ->willReturnCallback(function (Slug $slug, $pr_id, $comment) {
                $this->closedPrs[] = $pr_id;
            });
    }

    public function tearDown(): void
    {
        parent::tearDown();
        putenv('USE_CLOSE_NO_LONGER_RELEVANT');
    }

    public function testOutdatedClosed()
    {
        $this->runtestExpectedOutput();
        self::assertEquals($this->expectedClosedPrs, $this->closedPrs);
    }
}
