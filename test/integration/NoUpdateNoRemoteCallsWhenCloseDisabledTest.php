<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Ensures no-op runs short-circuit before remote provider lookups when
 * stale-PR cleanup is disabled.
 */
class NoUpdateNoRemoteCallsWhenCloseDisabledTest extends ComposerUpdateIntegrationBase
{
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.1.3';
    protected $packageVersionForToUpdateOutput = '1.1.4';
    protected $composerAssetFiles = 'composer-non-dev';

    public function testNoUpdateRunSkipsRemotePrLookupsWhenCleanupDisabled()
    {
        putenv('USE_CLOSE_NO_LONGER_RELEVANT');
        $this->mockProvider->expects($this->never())
            ->method('getDefaultBase');
        $this->mockProvider->expects($this->never())
            ->method('getPrsNamed');
        $this->mockProvider->expects($this->never())
            ->method('getBranchesFlattened');

        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage('No updates found', $this->cosy);
    }
}
