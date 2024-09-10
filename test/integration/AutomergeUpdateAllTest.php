<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test for automerge being enabled.
 */
class AutomergeUpdateAllTest extends AutoMergeBase
{
    protected $composerAssetFiles = 'composer.update_all_automerge';
    protected $hasUpdatedPsrLog = false;
    protected $hasUpdatedPsrCache = false;
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.1.3';
    protected $packageVersionForToUpdateOutput = '1.1.4';
    protected $hasAutoMerge = true;
    protected $checkPrUrl = true;
    protected $usesDirect = false;

    protected function getPrsNamed()
    {
        if (!$this->isUpdate) {
            return [];
        }
        return [
            'violinistall' => [
                'base' => [
                    'sha' => 456,
                ],
                'title' => 'not the same as the other',
                'number' => 666,
            ],
        ];
    }

    protected function createExpectedCommandForPackage($package)
    {
        return ['composer', 'update'];
    }
}
