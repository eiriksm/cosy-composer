<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test for automerge being enabled.
 */
class AutomergeMethodTest extends AutoMergeBase
{
    protected $composerAssetFiles = 'composer.automerge';
    protected $hasUpdatedPsrLog = false;
    protected $hasUpdatedPsrCache = false;
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.1.3';
    protected $packageVersionForToUpdateOutput = '1.1.4';
    protected $hasAutoMerge = true;
    protected $checkPrUrl = true;

    /**
     * @dataProvider getUpdateVariations
     */
    public function testAutomerge($should_have_updated)
    {
        parent::testAutomerge($should_have_updated);
        if ($should_have_updated) {
            self::assertEquals('squash', $this->autoMergeParams["merge_method"]);
        }
    }
}
