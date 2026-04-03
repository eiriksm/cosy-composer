<?php

namespace eiriksm\CosyComposerTest\unit;

use eiriksm\CosyComposer\Helpers;
use eiriksm\CosyComposer\Providers\NamedPrs;
use PHPUnit\Framework\TestCase;
use Violinist\Config\Config;

class HelperTest extends TestCase
{

    /**
     * Unit test for the createBranchName method.
     *
     * @dataProvider branchNameProvider
     */
    public function testBranchName($item, $one_per_package, $config, $expected)
    {
        $this->assertEquals($expected, Helpers::createBranchName($item, $one_per_package, $config));
    }

    /**
     * Unit test for the createBranchNameForGroup method.
     *
     * @dataProvider branchNameProviderGroup
     */
    public function testGroupBranch(\stdClass $rule, Config $config, $expected)
    {
        self::assertEquals($expected, Helpers::createBranchNameForGroup($rule, $config));
    }

    public static function branchNameProvider()
    {
        return [
            [
                (object) [
                    'name' => 'test',
                    'version' => '1.0.0',
                    'latest' => '1.0.2',
                ],
                false,
                null,
                'test100102',
            ],
            [
                (object) [
                    'name' => 'test',
                    'version' => '1.0.0',
                    'latest' => '1.0.2',
                ],
                true,
                null,
                'violinisttest',
            ],
            [
                (object) [
                    'name' => 'test',
                    'version' => '1.0.0',
                    'latest' => '1.0.2',
                ],
                false,
                Config::createFromComposerData((object) [
                    'extra' => (object) [
                        'violinist' => (object) [
                            'branch_prefix' => 'test-',
                        ],
                    ],
                ]),
                'test-test100102',
            ],
            [
                (object) [
                    'name' => 'test',
                    'version' => '1.0.0',
                    'latest' => '1.0.2',
                ],
                true,
                Config::createFromComposerData((object) [
                    'extra' => (object) [
                        'violinist' => (object) [
                            'branch_prefix' => 'test-',
                        ],
                    ],
                ]),
                'test-violinisttest',
            ],
        ];
    }

    public static function branchNameProviderGroup()
    {
        return [
            [
                (object) [
                    'name' => 'test',
                    'slug' => 'slug',
                ],
                Config::createFromComposerData((object) [
                    'extra' => (object) [
                        'violinist' => (object) [
                            'branch_prefix' => 'test-',
                        ],
                    ],
                ]),
                'test-slug',
            ],
            [
                (object) [
                    'name' => 'test',
                    'slug' => '',
                ],
                Config::createFromComposerData((object) [
                    'extra' => (object) [
                        'violinist' => (object) [
                            'branch_prefix' => 'test-',
                        ],
                    ],
                ]),
                'test-test',
            ],
            [
                (object) [
                    'name' => 'test',
                    'slug' => '',
                ],
                Config::createFromComposerData((object) []),
                'test',
            ],
            [
                (object) [
                    'name' => 'test',
                    'slug' => 'slug',
                ],
                Config::createFromComposerData((object) []),
                'slug',
            ],
        ];
    }

    /**
     * @dataProvider composerOutdatedCommandProvider
     */
    public function testCreateComposerOutdatedCommandFromConfig($outdated_flag, $direct, $expected)
    {
        $config = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getComposerOutdatedFlag'])
            ->getMock();
        $config
            ->expects(self::once())
            ->method('getComposerOutdatedFlag')
            ->willReturn($outdated_flag);

        self::assertSame($expected, Helpers::createComposerOutdatedCommandFromConfig($config, $direct));
    }

    /**
     * @dataProvider shouldUpdatePrProvider
     */
    public function testShouldUpdatePr($branch_name, $pr_params, NamedPrs $prs_named, $expected)
    {
        self::assertSame($expected, Helpers::shouldUpdatePr($branch_name, $pr_params, $prs_named));
    }

    public static function shouldUpdatePrProvider()
    {
        $base_pr = [
            'head' => ['ref' => 'my-branch'],
            'title' => 'Update package to 1.0.1',
            'body' => 'This updates package from 1.0.0 to 1.0.1.',
        ];

        return [
            'empty branch name returns false' => [
                '',
                ['title' => 'Update', 'body' => 'body'],
                NamedPrs::createFromArray([$base_pr]),
                false,
            ],
            'empty pr_params returns false' => [
                'my-branch',
                [],
                NamedPrs::createFromArray([$base_pr]),
                false,
            ],
            'no change returns false' => [
                'my-branch',
                ['title' => 'Update package to 1.0.1', 'body' => 'This updates package from 1.0.0 to 1.0.1.'],
                NamedPrs::createFromArray([$base_pr]),
                false,
            ],
            'title changed returns true' => [
                'my-branch',
                ['title' => 'Security update for package to 1.0.1', 'body' => 'This updates package from 1.0.0 to 1.0.1.'],
                NamedPrs::createFromArray([$base_pr]),
                true,
            ],
            'body changed returns true' => [
                'my-branch',
                ['title' => 'Update package to 1.0.1', 'body' => 'This updates package from 1.0.0 to 1.0.2 with additional packages.'],
                NamedPrs::createFromArray([$base_pr]),
                true,
            ],
            'both title and body changed returns true' => [
                'my-branch',
                ['title' => 'New title', 'body' => 'New body'],
                NamedPrs::createFromArray([$base_pr]),
                true,
            ],
            'branch not in named prs returns false' => [
                'unknown-branch',
                ['title' => 'Update', 'body' => 'body'],
                NamedPrs::createFromArray([$base_pr]),
                false,
            ],
            'body with whitespace difference returns false' => [
                'my-branch',
                ['title' => 'Update package to 1.0.1', 'body' => '  This updates package from 1.0.0 to 1.0.1.  '],
                NamedPrs::createFromArray([$base_pr]),
                false,
            ],
        ];
    }

    public static function composerOutdatedCommandProvider()
    {
        return [
            'patch and direct' => [
                'patch',
                true,
                ['composer', 'outdated', '--format=json', '--no-interaction', '--direct', '--patch-only'],
            ],
            'minor' => [
                'minor',
                false,
                ['composer', 'outdated', '--format=json', '--no-interaction', '--minor-only'],
            ],
            'major only' => [
                'major-only',
                false,
                ['composer', 'outdated', '--format=json', '--no-interaction', '--major-only'],
            ],
            'major' => [
                'major',
                true,
                ['composer', 'outdated', '--format=json', '--no-interaction', '--direct'],
            ],
            'unknown defaults to minor only' => [
                'unexpected',
                false,
                ['composer', 'outdated', '--format=json', '--no-interaction', '--minor-only'],
            ],
        ];
    }
}
