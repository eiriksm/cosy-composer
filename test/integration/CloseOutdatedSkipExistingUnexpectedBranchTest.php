<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test that we are closing PRs not the latest and greatest.
 */
class CloseOutdatedSkipExistingUnexpectedBranchTest extends CloseOutdatedBase
{
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.1.5';
    protected $composerAssetFiles = 'composer.close.outdated';
    protected $expectedClosedPrs = [123, 124, 125];

    public function testOutdatedClosed()
    {
        parent::testOutdatedClosed();
        self::assertNotEmpty($this->findMessage('Changing branch because of an unexpected update result. We expected the branch name to be psrlog100115 but instead we are now switching to psrlog100114.', $this->cosy));
        self::assertNotEmpty($this->findMessage('Skipping psr/log because a pull request already exists', $this->cosy));
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
            'psrcache100112' => [
                'number' => 224,
                'title' => 'Test update',
            ],
            'psrlog100111' => [
                'number' => 125,
                'title' => 'Test update',
            ],
        ];
    }

    protected function getBranchesFlattened()
    {
        return array_keys($this->getPrsNamed());
    }
}
