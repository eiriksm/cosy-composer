<?php

namespace eiriksm\CosyComposerTest\unit;

use eiriksm\CosyComposer\GroupUpdateItem;
use eiriksm\CosyComposer\IndividualUpdateItem;
use eiriksm\CosyComposer\Updater\IndividualUpdater;
use PHPUnit\Framework\TestCase;
use Violinist\Config\Config;

class IndividualUpdaterTest extends TestCase
{
    /**
     * @dataProvider getTestCreateGroups
     */
    public function testCreateGroups(array $items, Config $config, $expected)
    {
        $object_items = array_map(function ($item) {
            return new IndividualUpdateItem($item);
        }, $items);
        // Just throwing an invalid item in there for coverage.
        $object_items[] = 'invalid item';
        $actual = IndividualUpdater::createGroups($object_items, $config);
        self::assertEquals($expected, $actual);
    }

    public static function getTestCreateGroups()
    {
        $simple_rules_config = Config::createFromComposerData((object) [
            'extra' => (object) [
                'violinist' => (object) [
                    'rules' => [
                        (object) [
                            'name' => 'test',
                            'slug' => 'psr-log',
                            'matchRules' => [
                                (object) [
                                    'type' => 'names',
                                    'values' => [
                                        'psr/*',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $simple_group_item = new GroupUpdateItem((object) [
            'name' => 'test',
            'slug' => 'psr-log',
            'matchRules' => [
                (object) [
                    'type' => 'names',
                    'values' => [
                        'psr/*',
                    ],
                ],
            ],
        ], (object) [
            'name' => 'psr/log',
            'version' => '1.0.0',
            'latest' => '1.0.2',
        ], $simple_rules_config);
        $simple_group_item->addData((object) [
            'name' => 'psr/cache',
            'version' => '1.0.0',
            'latest' => '1.0.2',
        ]);
        $hash = md5(json_encode($simple_rules_config->getRules()[0]));
        return [
            [
                [
                    (object) [
                        'name' => 'psr/log',
                        'version' => '1.0.0',
                        'latest' => '1.0.2',
                    ],
                ],
                new Config(),
                [],
            ],
            [
                [
                    (object) [
                        'name' => 'psr/log',
                        'version' => '1.0.0',
                        'latest' => '1.0.2',
                    ],
                ],
                Config::createFromComposerData((object) [
                    'extra' => (object) [
                        'violinist' => (object) [
                            'rules' => [
                                (object) [
                                    'name' => 'test',
                                    'slug' => 'psr-log',
                                ],
                            ],
                        ],
                    ],
                ]),
                [],
            ],
            [
                [
                    (object) [
                        'name' => 'psr/log',
                        'version' => '1.0.0',
                        'latest' => '1.0.2',
                    ],
                    (object) [
                        'name' => 'psr/cache',
                        'version' => '1.0.0',
                        'latest' => '1.0.2',
                    ],
                ],
                $simple_rules_config,
                [
                     $hash => $simple_group_item,
                ],
            ],
        ];
    }
}
