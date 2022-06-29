<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test for using the dev deps config option to 0.
 */
class DevDepsRemovedIndirectTest extends ComposerUpdateIntegrationBase
{
    protected $usesDirect = false;
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.0.1';
    protected $packageVersionForToUpdateOutput = '1.1.4';
    protected $composerAssetFiles = 'composer-filter-dev-indirect';

    public function testDevNameNotFail()
    {
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage('No updates found', $this->cosy);
    }
}
