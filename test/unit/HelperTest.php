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
}
