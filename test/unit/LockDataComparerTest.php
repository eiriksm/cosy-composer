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
            // Dev package with both dist and source references changed.
            [
                (object) [
                    'packages' => [],
                    'packages-dev' => [
                        (object) [
                            'version' => 'dev-master',
                            'name' => 'psr/log',
                            'dist' => (object) [
                                'reference' => 'aaa111',
                            ],
                            'source' => (object) [
                                'reference' => 'aaa111',
                            ],
                        ],
                    ],
                ],
                (object) [
                    'packages' => [],
                    'packages-dev' => [
                        (object) [
                            'version' => 'dev-master',
                            'name' => 'psr/log',
                            'dist' => (object) [
                                'reference' => 'bbb222',
                            ],
                            'source' => (object) [
                                'reference' => 'bbb222',
                            ],
                        ],
                    ],
                ],
                [
                    new \eiriksm\ViolinistMessages\UpdateListItem('psr/log', 'dev-master#bbb222', 'dev-master#aaa111'),
                ],
            ],
            // Dev package where only source has references (dist is empty).
            [
                (object) [
                    'packages' => [],
                    'packages-dev' => [
                        (object) [
                            'version' => 'dev-master',
                            'name' => 'psr/log',
                            'source' => (object) [
                                'reference' => 'aaa111',
                            ],
                        ],
                    ],
                ],
                (object) [
                    'packages' => [],
                    'packages-dev' => [
                        (object) [
                            'version' => 'dev-master',
                            'name' => 'psr/log',
                            'source' => (object) [
                                'reference' => 'bbb222',
                            ],
                        ],
                    ],
                ],
                [
                    new \eiriksm\ViolinistMessages\UpdateListItem('psr/log', 'dev-master#bbb222', 'dev-master#aaa111'),
                ],
            ],
            // Dev package where only dist has references (source is empty).
            [
                (object) [
                    'packages' => [],
                    'packages-dev' => [
                        (object) [
                            'version' => 'dev-master',
                            'name' => 'psr/log',
                            'dist' => (object) [
                                'reference' => 'aaa111',
                            ],
                        ],
                    ],
                ],
                (object) [
                    'packages' => [],
                    'packages-dev' => [
                        (object) [
                            'version' => 'dev-master',
                            'name' => 'psr/log',
                            'dist' => (object) [
                                'reference' => 'bbb222',
                            ],
                        ],
                    ],
                ],
                [
                    new \eiriksm\ViolinistMessages\UpdateListItem('psr/log', 'dev-master#bbb222', 'dev-master#aaa111'),
                ],
            ],
            // Dev package where dist and source references are the same (no update).
            [
                (object) [
                    'packages' => [],
                    'packages-dev' => [
                        (object) [
                            'version' => 'dev-master',
                            'name' => 'psr/log',
                            'dist' => (object) [
                                'reference' => 'aaa111',
                            ],
                            'source' => (object) [
                                'reference' => 'aaa111',
                            ],
                        ],
                    ],
                ],
                (object) [
                    'packages' => [],
                    'packages-dev' => [
                        (object) [
                            'version' => 'dev-master',
                            'name' => 'psr/log',
                            'dist' => (object) [
                                'reference' => 'aaa111',
                            ],
                            'source' => (object) [
                                'reference' => 'aaa111',
                            ],
                        ],
                    ],
                ],
                [],
            ],
            // Dev package where dist is the same but source differs.
            [
                (object) [
                    'packages' => [],
                    'packages-dev' => [
                        (object) [
                            'version' => 'dev-master',
                            'name' => 'psr/log',
                            'dist' => (object) [
                                'reference' => 'aaa111',
                            ],
                            'source' => (object) [
                                'reference' => 'aaa111',
                            ],
                        ],
                    ],
                ],
                (object) [
                    'packages' => [],
                    'packages-dev' => [
                        (object) [
                            'version' => 'dev-master',
                            'name' => 'psr/log',
                            'dist' => (object) [
                                'reference' => 'aaa111',
                            ],
                            'source' => (object) [
                                'reference' => 'bbb222',
                            ],
                        ],
                    ],
                ],
                [
                    new \eiriksm\ViolinistMessages\UpdateListItem('psr/log', 'dev-master#bbb222', 'dev-master#aaa111'),
                ],
            ],
        ];
    }
}
