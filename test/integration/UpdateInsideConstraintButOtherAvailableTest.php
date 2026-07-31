<?php

namespace eiriksm\CosyComposerTest\integration;

class UpdateInsideConstraintButOtherAvailableTest extends ComposerUpdateIntegrationBase
{
    protected ?string $packageForUpdateOutput = 'drupal/core-recommended';
    protected ?string $packageVersionForFromUpdateOutput = '10.0.9';
    protected ?string $packageVersionForToUpdateOutput = '10.1.2';
    protected ?string $composerAssetFiles = 'composer.beyond.other_available';
    protected $checkPrUrl = false;

    public function testUpdate()
    {
        $this->runtestExpectedOutput();
        $output = $this->cosy->getOutput();
        $this->assertOutputContainsMessage('Creating pull request from drupalcorerecommended100910010', $this->cosy);
    }
}
