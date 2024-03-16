<?php

namespace eiriksm\CosyComposerTest\integration;

class ComposerUpdateFailsTest extends ComposerUpdateIntegrationBase
{
    protected $composerAssetFiles = 'composer-psr-log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.0.2';
    protected $packageForUpdateOutput = 'psr/log';

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
