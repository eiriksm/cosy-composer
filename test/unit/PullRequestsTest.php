<?php

namespace eiriksm\CosyComposerTest\unit;

use eiriksm\CosyComposer\PrParamsCreator;
use eiriksm\CosyComposerTest\GetCosyTrait;
use eiriksm\ViolinistMessages\ViolinistMessages;
use PHPUnit\Framework\TestCase;

class PullRequestsTest extends TestCase
{
    use GetCosyTrait;

    public function testPullrequestTitle()
    {
        $pr_params = new PrParamsCreator(new ViolinistMessages());
        $item = (object) [
            'name' => 'test/package',
            'version' => '1.0.0',
        ];
        $post_update = (object) [
            // I mean, even if we have a newline.
            'version' => "1.0.1\n",
        ];

        $title = $pr_params->createTitle($item, $post_update);
        $this->assertEquals('Update test/package from 1.0.0 to 1.0.1', $title);
    }
}
