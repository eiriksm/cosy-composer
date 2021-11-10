<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test for a default commit message.
 */
class AllowListTest extends ComposerUpdateIntegrationBase
{
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.0.2';
    protected $composerAssetFiles = 'composer-commit';
    protected $hasUpdatedPsrLog = false;

    public function testAllowList()
    {
        $this->runtestExpectedOutput();
        self::assertEquals($this->hasUpdatedPsrLog, false);
    }

}
