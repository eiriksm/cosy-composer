<?php

namespace eiriksm\CosyComposerTest\unit;

use eiriksm\CosyComposer\CosyComposer;
use eiriksm\CosyComposerTest\GetCosyTrait;
use eiriksm\ViolinistMessages\ViolinistMessages;
use PHPUnit\Framework\TestCase;

class PullRequestsTest extends TestCase
{
    use GetCosyTrait;

    public function testPullrequestTitle()
    {
        // Use reflection to invoke the protected method we want to test.
        $class = new \ReflectionClass(CosyComposer::class);
        $method = $class->getMethod('createTitle');
        $method->setAccessible(true);
        $mock_cosy = $this->getMockCosy();
        $item = (object) [
            'name' => 'test/package',
            'version' => '1.0.0',
        ];
        $post_update = (object) [
            // I mean, even if we have a newline.
            'version' => "1.0.1\n",
        ];

        $title = $method->invokeArgs($mock_cosy, [$item, $post_update]);
        $this->assertEquals('Update test/package from 1.0.0 to 1.0.1', $title);
    }
}
