<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test for automerge being enabled.
 */
class AutomergeTest extends ComposerUpdateIntegrationBase
{
    protected $composerAssetFiles = 'composer.automerge';
    protected $hasUpdatedPsrLog = false;
    protected $hasUpdatedPsrCache = false;
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.1.3';
    protected $packageVersionForToUpdateOutput = '1.1.4';
    protected $hasAutoMerge = true;
    protected $checkPrUrl = true;

    public function testAutomerge()
    {
        $this->runtestExpectedOutput();
    }
}
