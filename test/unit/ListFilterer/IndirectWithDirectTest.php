<?php

namespace eiriksm\CosyComposerTest\unit\ListFilterer;

use eiriksm\CosyComposer\ListFilterer\IndirectWithDirectFilterer;
use PHPUnit\Framework\TestCase;

class IndirectWithDirectTest extends TestCase
{
    /**
     * @dataProvider getNoneFilteredOptions
     */
    public function testNoneFiltered($lock, $json)
    {
        $list = [
            (object) [
                'name' => 'psr/log',
                'version' => '1.0.0',
                'latest' => '3.0.0',
                'latest-status' => "semver-safe-update",
                'child_with_update' => 'psr/log',
                'child_latest' => '3.0.0',
                'children_with_update' => [
                    'psr/log',
                ]
            ],
        ];
        $filterer = IndirectWithDirectFilterer::create($lock, $json);
        $new_list = $filterer->filter($list);
        self::assertEquals(count($list), count($new_list));
        self::assertEquals($list, $new_list);
    }

    public function getNoneFilteredOptions()
    {
        return [
            [
                'lock' => (object) [
                    'packages' => [
                        (object) [
                            'name' => 'psr/log',
                            'version' => '1.0.0',
                        ],
                    ],
                    'packages-dev' => [],
                ],
                'json' => (object) [
                    'require' => (object) [
                        'psr/log' => '1.0.0',
                    ]
                ],
            ],
            [
                'lock' => (object) [
                    'packages' => [
                        (object) [
                            'name' => 'psr/log',
                            'version' => '1.0.0',
                        ],
                        (object) [
                            'name' => 'psr/cache',
                            'version' => '1.0.0',
                        ],
                    ],
                    'packages-dev' => [],
                ],
                'json' => (object) [
                    'require' => (object) [
                        'psr/log' => '~1.0.0',
                        'psr/cache' => '~1.0.0',
                    ]
                ],
            ],
        ];
    }
}
