<?php

namespace eiriksm\CosyComposerTest\integration;

use Github\Exception\ValidationFailedException;
use Violinist\Slug\Slug;
use Violinist\SymfonyCloudSecurityChecker\SecurityChecker;

/**
 * Test for automerge being enabled.
 */
class AutomergeUpdateAllSecTest extends AutoMergeBase
{
    protected $composerAssetFiles = 'composer.automerge_update_all_sec';
    protected $hasUpdatedPsrLog = false;
    protected $hasUpdatedPsrCache = false;
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.1.3';
    protected $packageVersionForToUpdateOutput = '1.1.4';
    protected $hasAutoMerge = true;
    protected $checkPrUrl = true;
    protected $usesDirect = false;

    public function setUp()
    {
        parent::setUp();
        $checker = $this->createMock(SecurityChecker::class);
        $checker->method('checkDirectory')
            ->willReturn([
                'psr/log' => true,
            ]);
        $this->cosy->getCheckerFactory()->setChecker($checker);
    }

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
        return 'composer update';
    }
}
