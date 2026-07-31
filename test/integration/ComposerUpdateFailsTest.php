<?php

namespace eiriksm\CosyComposerTest\integration;

class ComposerUpdateFailsTest extends ComposerUpdateIntegrationBase
{
    protected ?string $composerAssetFiles = 'composer-psr-log';
    protected ?string $packageVersionForFromUpdateOutput = '1.0.0';
    protected ?string $packageVersionForToUpdateOutput = '1.0.2';
    protected ?string $packageForUpdateOutput = 'psr/log';

    public function testUpdatesFoundButComposerUpdateFails()
    {
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage('Caught an exception: Composer update exited with exit code 1', $this->cosy);
    }

    protected function handleExecutorReturnCallback($cmd, &$return)
    {
        if ($cmd == $this->createExpectedCommandForPackage('psr/log')) {
            $return = 1;
        }
    }
}
