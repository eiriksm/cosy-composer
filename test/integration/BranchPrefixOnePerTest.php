<?php

namespace eiriksm\CosyComposerTest\integration;

use Bitbucket\Api\Repositories;
use Bitbucket\Client;
use eiriksm\CosyComposer\CommandExecuter;
use eiriksm\CosyComposer\CosyComposer;
use eiriksm\CosyComposer\ProviderFactory;
use eiriksm\CosyComposer\Providers\Bitbucket;
use eiriksm\CosyComposer\Providers\Github;
use eiriksm\CosyComposerTest\integration\Base;
use eiriksm\CosyComposerTest\integration\ComposerUpdateIntegrationBase;

/**
 * Test for branch prefix with one_per option set.
 */
class BranchPrefixOnePerTest extends ComposerUpdateIntegrationBase
{
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.1.4';
    protected $composerAssetFiles = 'composerbranch.one_per';
    protected $checkPrUrl = true;

    public function testBranchPrefixUsed()
    {
        $this->runtestExpectedOutput();
        self::assertEquals('my_prefixviolinistpsrlog', $this->prParams["head"]);
    }
}
