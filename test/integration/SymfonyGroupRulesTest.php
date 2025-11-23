<?php

namespace eiriksm\CosyComposerTest\integration;

class SymfonyGroupRulesTest extends ComposerUpdateIntegrationBase
{
    protected $composerAssetFiles = 'symfony-group';
    protected $packageForUpdateOutput = 'symfony/console';
    protected $packageVersionForFromUpdateOutput = '5.4.0';
    protected $packageVersionForToUpdateOutput = '5.4.1';
    protected $hasAutoMerge = true;
    protected $checkPrUrl = true;

    public function testSymfonyGroupWithAutoMerge()
    {
        $this->runtestExpectedOutput();
        self::assertEquals($this->prParams['head'], 'symfony-group');
        self::assertEquals($this->prParams["title"], 'Update group `Symfony packages`');
    }
}