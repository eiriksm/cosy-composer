<?php

namespace eiriksm\CosyComposerTest\integration;

class WordpressPluginGroupRuleTest extends ComposerUpdateIntegrationBase
{
    protected ?string $composerAssetFiles = 'composer-wordpress-plugin-group';
    protected ?string $packageForUpdateOutput = 'wpackagist-plugin/akismet';
    protected ?string $packageVersionForFromUpdateOutput = '5.3';
    protected ?string $packageVersionForToUpdateOutput = '5.3.1';
    protected $checkPrUrl = true;

    public function testWordpressPluginGroup()
    {
        $this->runtestExpectedOutput();
        self::assertEquals('wordpress-plugins', $this->prParams['head']);
        self::assertEquals('Update group `WordPress Plugins`', $this->prParams["title"]);
    }
}
