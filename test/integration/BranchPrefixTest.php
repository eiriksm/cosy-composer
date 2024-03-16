<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposerTest\integration\ComposerUpdateIntegrationBase;

/**
 * Test for issue 164.
 */
class BranchPrefixTest extends ComposerUpdateIntegrationBase
{
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.1.4';
    protected $composerAssetFiles = 'composerbranch';
    protected $checkPrUrl = true;

    public function testBranchPrefixUsed()
    {
        $this->runtestExpectedOutput();
        self::assertEquals('my_prefixpsrlog100114', $this->prParams["head"]);
    }

    public function testBranchPrefixUsedNotLatest()
    {
        // Set latest version to be newer than the updated version
        $this->packageVersionForToUpdateOutput = '1.2.4';
        $this->setUp();
        $this->runtestExpectedOutput();
        self::assertEquals('my_prefixpsrlog100114', $this->prParams["head"]);
    }
}
