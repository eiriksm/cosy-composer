<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test for branch prefix with one_per option set.
 *
 * Plus if the dependency was updated to something else than we expect it. Then let's use the same expected branch then
 * as well.
 */
class BranchPrefixOnePerUnexpectedUpdateTest extends ComposerUpdateIntegrationBase
{
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.1.3';
    protected $composerAssetFiles = 'composerbranch.one_per';
    protected $checkPrUrl = true;

    public function testBranchPrefixUsedAndOnePer()
    {
        $this->runtestExpectedOutput();
        self::assertEquals('my_prefixviolinistpsrlog', $this->prParams["head"]);
    }
}
