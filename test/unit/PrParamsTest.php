<?php

namespace eiriksm\CosyComposerTest\unit;

use eiriksm\CosyComposer\PrParamsCreator;
use eiriksm\ViolinistMessages\ViolinistMessages;
use eiriksm\ViolinistMessages\ViolinistUpdate;
use PHPUnit\Framework\TestCase;
use Violinist\Config\Config;
use Violinist\Slug\Slug;

class PrParamsTest extends TestCase
{
    /**
     * Test the PR params.
     *
     * @dataProvider prParamsProvider
     */
    public function testPrParams(bool $is_private, Slug $slug, Config $config, $expected) : void
    {
        $msg_factory = new ViolinistMessages();
        $pr_params_creator = new PrParamsCreator($msg_factory);
        // Create some markdown that also includes that kind of summary detail
        // kind of a thing.
        $update = new ViolinistUpdate();
        $update->setName('test/package');
        $update->setCurrentVersion('1.0.0');
        $update->setNewVersion('1.0.1');
        $update->setPackageReleaseNotes([
            '- [Release notes for tag 1.0.1](https://github.com/test/package/releases/tag/1.0.1)',
        ]);
        $body = $msg_factory->getPullRequestBody($update);
        $fork_user = 'test';
        $branch_name = 'derptest';
        $title = $msg_factory->getPullRequestTitle($update);
        $default_branch = 'develop';
        $pr_params = $pr_params_creator->getPrParams($fork_user, $is_private, $slug, $branch_name, $body, $title, $default_branch, $config);
        self::assertEquals($expected['title'], $pr_params['title']);
        self::assertEquals($expected['base'], $pr_params['base']);
        self::assertEquals($expected['head'], $pr_params['head']);
        if ($slug->getProvider() === 'bitbucket.org') {
            self::assertStringNotContainsString('<details>', $pr_params['body']);
        }
    }

    /**
     * Test the PR params when there is a group.
     *
     * For now this actually reuses the same as the regular params.
     *
     * @dataProvider prParamsProvider
     */
    public function testPrParamsForGroup(bool $is_private, Slug $slug, Config $config, $expected) : void
    {
        $this->testPrParams($is_private, $slug, $config, $expected);
    }

    public static function prParamsProvider()
    {
        return [
            [
                true,
                new Slug(),
                new Config(),
                [
                    'base' => 'develop',
                    'head' => 'derptest',
                    'title' => 'Update test/package from 1.0.0 to 1.0.1
',
                ],
            ],
            [
                false,
                new Slug(),
                new Config(),
                [
                    'base' => 'develop',
                    'head' => 'test:derptest',
                    'title' => 'Update test/package from 1.0.0 to 1.0.1
',
                ],
            ],
            [
                false,
                Slug::createFromUrl('https://bitbucket.org/test/repo'),
                new Config(),
                [
                    'base' => 'develop',
                    'head' => 'test:derptest',
                    'title' => 'Update test/package from 1.0.0 to 1.0.1
',
                ],
            ],
        ];
    }
}
