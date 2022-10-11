<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test that we are closing PRs not the latest and greatest.
 */
class CloseOutdatedSkipExistingTest extends CloseOutdatedBase
{
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.1.4';
    protected $composerAssetFiles = 'composer.close.outdated';
    protected $expectedClosedPrs = [123, 124, 125];

    public function testOutdatedClosed()
    {
        parent::testOutdatedClosed();
        self::assertNotEmpty($this->findMessage('No updates that have not already been pushed.', $this->cosy));
    }

    public function testOutdatedNoDefaultBase()
    {
        $this->defaultSha = null;
        $this->testOutdatedClosed();
    }

    protected function getPrsNamed()
    {
        return [
            'psrlog100114' => [
                'base' => [
                    'sha' => 123,
                ],
                'number' => 456,
                'title' => 'Update psr/log from 1.0.0 to 1.1.4',
            ],
            'psrlog100113' => [
                'number' => 123,
                'title' => 'Test update',
            ],
            'psrlog100112' => [
                'number' => 124,
                'title' => 'Test update',
            ],
            'psrlog100111' => [
                'number' => 125,
                'title' => 'Test update',
            ]
        ];
    }

    protected function getBranchesFlattened()
    {
        return array_keys($this->getPrsNamed());
    }
}
