<?php

namespace eiriksm\CosyComposerTest\integration;

class CommitTimestampLoggedTest extends ComposerUpdateIntegrationBase
{
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.1.4';
    protected $composerAssetFiles = 'composer.close.outdated';

    protected function setDummyGithubProvider(): void
    {
        parent::setDummyGithubProvider();
        $this->getMockProvider()
            ->method('getDefaultBaseTimestamp')
            ->willReturn('2025-01-15T10:30:00Z');
    }

    public function testTimestampIsLogged(): void
    {
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage('Current commit timestamp for master is 2025-01-15T10:30:00Z', $this->cosy);
    }
}
