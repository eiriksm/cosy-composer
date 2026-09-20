<?php

namespace eiriksm\CosyComposerTest\integration;

class ErrorCommittingTest extends ComposerUpdateIntegrationBase
{
    protected ?string $composerAssetFiles = 'composer-psr-log';
    protected ?string $packageVersionForFromUpdateOutput = '1.0.0';
    protected ?string $packageVersionForToUpdateOutput = '1.0.2';
    protected ?string $packageForUpdateOutput = 'psr/log';

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
