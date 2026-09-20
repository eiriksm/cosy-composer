<?php

namespace eiriksm\CosyComposerTest\integration;

class DrupalContribExcludingCoreWildcardTest extends ComposerUpdateIntegrationBase
{
    protected ?string $composerAssetFiles = 'composer-drupal-core-wildcard-exclusion';
    protected ?string $packageForUpdateOutput = 'drupal/coffee';
    protected ?string $packageVersionForFromUpdateOutput = '2.0.0';
    protected ?string $packageVersionForToUpdateOutput = '2.0.1';

    /**
     * @var bool
     */
    protected $checkPrUrl = true;

    public function testDrupalContribGroupExcludesCoreByWildcard(): void
    {
        $this->runtestExpectedOutput();
        self::assertEquals('drupal-contrib-only', $this->prParams['head']);
        self::assertEquals('Update group `Drupal Contrib Only`', $this->prParams["title"]);
    }
}
