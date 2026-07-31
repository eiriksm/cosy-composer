<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test for using the dev deps config option to 0.
 */
class DevDepsRemovedIndirectTest extends ComposerUpdateIntegrationBase
{
    protected $usesDirect = false;
    protected ?string $packageForUpdateOutput = 'psr/log';
    protected ?string $packageVersionForFromUpdateOutput = '1.0.1';
    protected ?string $packageVersionForToUpdateOutput = '1.1.4';
    protected ?string $composerAssetFiles = 'composer-filter-dev-indirect';

    public function testDevNameNotFail()
    {
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage('No updates found', $this->cosy);
    }
}
