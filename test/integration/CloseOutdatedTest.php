<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test that we are closing PRs not the latest and greatest.
 */
class CloseOutdatedTest extends CloseOutdatedBase
{
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.1.4';
    protected $composerAssetFiles = 'composer.close.outdated';
    protected $checkPrUrl = true;
    protected $expectedClosedPrs = [124, 125];

    protected function getPrsNamed()
    {
        return [
            'psrlog100114' => [
                'number' => 456,
                'title' => 'Test update',
                'base' => [
                    'ref' => 'master',
                ],
            ],
            'psrlog100113' => [
                'number' => 123,
                'title' => 'Test update',
                'base' => [
                    'ref' => 'notmaster',
                ],
            ],
            'psrlog100112' => [
                'number' => 124,
                'title' => 'Test update',
                'base' => [
                    'ref' => 'master',
                ],
            ],
            'psrlog100111' => [
                'number' => 125,
                'title' => 'Test update',
                'base' => [
                    'ref' => 'master',
                ],
            ],
        ];
    }
}
