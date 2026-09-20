<?php

namespace eiriksm\CosyComposerTest\integration;

class CommitTimestampLoggedTest extends ComposerUpdateIntegrationBase
{
    protected ?string $packageForUpdateOutput = 'psr/log';
    protected ?string $packageVersionForFromUpdateOutput = '1.0.0';
    protected ?string $packageVersionForToUpdateOutput = '1.1.4';
    protected ?string $composerAssetFiles = 'composer.close.outdated';

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
