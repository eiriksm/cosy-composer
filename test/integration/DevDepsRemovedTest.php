<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test for using the dev deps config option to 0.
 */
class DevDepsRemovedTest extends ComposerUpdateIntegrationBase
{
    protected ?string $packageForUpdateOutput = 'psr/log';
    protected ?string $packageVersionForFromUpdateOutput = '1.1.3';
    protected ?string $packageVersionForToUpdateOutput = '1.1.4';
    protected ?string $composerAssetFiles = 'composer-non-dev';

    public function testDevNameNotFail()
    {
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage('No updates found', $this->cosy);
    }
}
