<?php

namespace eiriksm\CosyComposerTest\unit;

use eiriksm\CosyComposer\LockDataComparer;
use PHPUnit\Framework\TestCase;

class LockDataComparerTest extends TestCase
{
    /**
     * @dataProvider getTestLockData
     */
    public function testLockData(\stdClass $before, \stdClass $after, $expected_update_list)
    {
        $comparer = new LockDataComparer($before, $after);
        self::assertEquals($expected_update_list, $comparer->getUpdateList());
    }

    public function getTestLockData() : array
    {
        return [
            [
                (object) [
                    'packages' => [],
                    'packages-dev' => [],
                ],
                (object) [
                    'packages' => [],
                    'packages-dev' => [],
                ],
                [],
            ],
            [
                (object) [
                    'packages' => [
                        (object) [
                            'version' => '1.0.0',
                            'name' => 'psr/log',
                        ],
                    ],
                    'packages-dev' => [],
                ],
                (object) [
                    'packages' => [
                        (object) [
                            'version' => '1.0.0',
                            'name' => 'psr/log',
                        ],
                    ],
                    'packages-dev' => [],
                ],
                [],
            ],
        ];
    }
}
