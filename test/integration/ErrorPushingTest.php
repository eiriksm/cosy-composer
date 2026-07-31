<?php

namespace eiriksm\CosyComposerTest\integration;

class ErrorPushingTest extends ComposerUpdateIntegrationBase
{
    protected ?string $composerAssetFiles = 'composer-psr-log';
    protected ?string $packageVersionForFromUpdateOutput = '1.0.0';
    protected ?string $packageVersionForToUpdateOutput = '1.0.2';
    protected ?string $packageForUpdateOutput = 'psr/log';

    public function testUpdatesRunButErrorPushing()
    {
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage('Caught an exception: Could not push to psrlog100102', $this->cosy);
    }

    protected function handleExecutorReturnCallback($cmd, &$return)
    {
        if ($cmd == ['git', 'push', 'origin', 'psrlog100102', '--force']) {
            $return = 1;
        }
    }
}
