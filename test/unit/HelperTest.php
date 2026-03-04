<?php

namespace eiriksm\CosyComposerTest\unit;

use eiriksm\CosyComposer\Helpers;
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

    public static function composerOutdatedCommandProvider()
    {
        return [
            'patch and direct' => [
                'patch',
                '--direct',
                ['composer', 'outdated', '--format=json', '--no-interaction', '--direct', '--patch-only'],
            ],
            'minor' => [
                'minor',
                null,
                ['composer', 'outdated', '--format=json', '--no-interaction', '--minor-only'],
            ],
            'major only' => [
                'major-only',
                null,
                ['composer', 'outdated', '--format=json', '--no-interaction', '--major-only'],
            ],
            'major' => [
                'major',
                '--direct',
                ['composer', 'outdated', '--format=json', '--no-interaction', '--direct'],
            ],
            'unknown defaults to minor only' => [
                'unexpected',
                null,
                ['composer', 'outdated', '--format=json', '--no-interaction', '--minor-only'],
            ],
            'null defaults to minor only' => [
                null,
                null,
                ['composer', 'outdated', '--format=json', '--no-interaction', '--minor-only'],
            ],
        ];
    }
}
