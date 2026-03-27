<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Ensures no-op runs short-circuit before remote provider lookups when
 * stale-PR cleanup is disabled.
 */
class NoUpdateNoRemoteCallsWhenCloseDisabledTest extends ComposerUpdateIntegrationBase
{
    protected $composerAssetFiles = 'composer-non-dev';

    public function setUp() : void
    {
        parent::setUp();
        putenv('USE_CLOSE_NO_LONGER_RELEVANT');
        $this->updateJson = '{"installed": []}';
    }

    public function tearDown(): void
    {
        parent::tearDown();
        putenv('USE_CLOSE_NO_LONGER_RELEVANT');
    }

    public function testNoUpdateRunSkipsRemotePrLookupsWhenCleanupDisabled()
    {
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
