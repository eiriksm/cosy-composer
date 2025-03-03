<?php

namespace eiriksm\CosyComposerTest\integration;

class ErrorCommittingTest extends ComposerUpdateIntegrationBase
{
    protected $composerAssetFiles = 'composer-psr-log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.0.2';
    protected $packageForUpdateOutput = 'psr/log';

    public function testUpdatesRunButErrorCommiting()
    {
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage('Caught an exception: Error committing the composer files. They are probably not changed.', $this->cosy);
    }

    protected function handleExecutorReturnCallback($cmd, &$return)
    {
        $command_string = implode(' ', $cmd);
        if (strpos($command_string, 'git commit composer.json composer.lock -m Update psr/log') === 0) {
            $return = 1;
        }
    }
}
