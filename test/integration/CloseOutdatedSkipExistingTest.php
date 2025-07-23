<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\Providers\NamedPrs;

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

    protected function getPrsNamed() : NamedPrs
    {
        $named_prs = new NamedPrs();
        $fake_commit = 'test commit
------
update_data:
  package: psr/log';
        $pr_array = [
            'psrlog100114' => [
                'base' => [
                    'sha' => 123,
                ],
                'number' => 456,
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
        ];
        foreach ($pr_array as $value) {
            $named_prs->addFromCommit($fake_commit, $value);
        }
        return $named_prs;
    }
}
