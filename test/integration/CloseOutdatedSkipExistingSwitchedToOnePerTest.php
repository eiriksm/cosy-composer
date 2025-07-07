<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\Providers\NamedPrs;

/**
 * Test that we are closing PRs not the latest and greatest.
 */
class CloseOutdatedSkipExistingSwitchedToOnePerTest extends CloseOutdatedBase
{
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.1.4';
    protected $composerAssetFiles = 'composer.close.outdated.one_per';
    protected $expectedClosedPrs = [356, 123, 124, 125];

    public function testOutdatedClosed()
    {
        parent::testOutdatedClosed();
    }

    protected function getPrsNamed() : NamedPrs
    {
        return NamedPrs::createFromArray([
            'violinistpsrlog' => [
                'base' => [
                    'sha' => 123,
                ],
                'number' => 456,
                'title' => 'Update psr/log from 1.0.0 to 1.1.4',
                'head' => [
                    'ref' => 'violinistpsrlog',
                ],
            ],
            'psrlog100114' => [
                'base' => [
                    'sha' => 123,
                ],
                'number' => 356,
                'title' => 'Update psr/log from 1.0.0 to 1.1.4',
                'head' => [
                    'ref' => 'psrlog100114',
                ],
            ],
            'psrlog100113' => [
                'number' => 123,
                'title' => 'Test update',
                'head' => [
                    'ref' => 'psrlog100113',
                ],
            ],
            'psrlog100112' => [
                'number' => 124,
                'title' => 'Test update',
                'head' => [
                    'ref' => 'psrlog100112',
                ],
            ],
            'psrlog100111' => [
                'number' => 125,
                'title' => 'Test update',
                'head' => [
                    'ref' => 'psrlog100111',
                ],
            ],
        ]);
    }
}
