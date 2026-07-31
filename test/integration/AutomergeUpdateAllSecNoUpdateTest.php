<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\Providers\NamedPrs;

/**
 * Test for automerge being enabled.
 */
class AutomergeUpdateAllSecNoUpdateTest extends AutoMergeBase
{
    protected ?string $composerAssetFiles = 'composer.automerge_update_all_sec';
    protected $hasUpdatedPsrLog = false;
    protected $hasUpdatedPsrCache = false;
    protected ?string $packageForUpdateOutput = 'psr/log';
    protected ?string $packageVersionForFromUpdateOutput = '1.1.3';
    protected ?string $packageVersionForToUpdateOutput = '1.1.4';
    protected $hasAutoMerge = false;
    protected $checkPrUrl = true;
    protected $usesDirect = false;

    protected function getPrsNamed() : NamedPrs
    {
        if (!$this->isUpdate) {
            return NamedPrs::createFromArray([]);
        }
        return NamedPrs::createFromArray([
            'violinistall' => [
                'base' => [
                    'sha' => 456,
                ],
                'head' => [
                    'ref' => 'violinistall',
                ],
                'title' => 'not the same as the other',
                'number' => 666,
            ],
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected function createExpectedCommandForPackage(string $package) : array
    {
        return ['composer', 'update'];
    }
}
