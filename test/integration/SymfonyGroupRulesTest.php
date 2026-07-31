<?php

namespace eiriksm\CosyComposerTest\integration;

class SymfonyGroupRulesTest extends ComposerUpdateIntegrationBase
{
    protected ?string $composerAssetFiles = 'symfony-group';
    protected ?string $packageForUpdateOutput = 'symfony/console';
    protected ?string $packageVersionForFromUpdateOutput = '5.4.0';
    protected ?string $packageVersionForToUpdateOutput = '5.4.1';
    protected $hasAutoMerge = true;
    protected $checkPrUrl = true;

    public function testSymfonyGroupWithAutoMerge()
    {
        $this->runtestExpectedOutput();
        self::assertEquals($this->prParams['head'], 'symfony-group');
        self::assertEquals($this->prParams["title"], 'Update group `Symfony packages`');
    }
}
