<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test for automerge method configuration.
 */
class AutomergeMethodTest extends AutoMergeBase
{
    protected ?string $composerAssetFiles = 'composer.automerge';
    protected $hasUpdatedPsrLog = false;
    protected $hasUpdatedPsrCache = false;
    protected ?string $packageForUpdateOutput = 'psr/log';
    protected ?string $packageVersionForFromUpdateOutput = '1.1.3';
    protected ?string $packageVersionForToUpdateOutput = '1.1.4';
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
