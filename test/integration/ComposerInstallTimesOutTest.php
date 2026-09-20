<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\Exceptions\ComposerInstallException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class ComposerInstallTimesOutTest extends ComposerUpdateIntegrationBase
{
    protected ?string $packageVersionForFromUpdateOutput = '1.0.0';
    protected ?string $packageVersionForToUpdateOutput = '1.0.1';
    protected ?string $packageForUpdateOutput = 'psr/log';
    protected ?string $composerAssetFiles = 'composer-psr-log-with-no-scripts';
    protected string $superDistinctCommandName = 'my-special-command-we-will-use-in-searching-the-logs';

    public function testNoScriptsPassed() : void
    {
        self::expectException(ComposerInstallException::class);
        self::expectExceptionMessageMatches(sprintf('/.*%s.*timeout.*/', $this->superDistinctCommandName));
        $this->runtestExpectedOutput();
    }

    protected function placeInitialComposerLock() : void
    {
        $this->placeComposerLockContentsFromFixture('composer-psr-log.lock', $this->dir);
    }

    protected function placeUpdatedComposerLock() : void
    {
        $this->placeComposerLockContentsFromFixture('composer-psr-log.lock.updated', $this->dir);
    }

    protected function handleExecutorReturnCallback(array $cmd, &$return) : void
    {
        $asString = implode(' ', $cmd);
        if (str_starts_with($asString, 'composer install')) {
            throw new ProcessTimedOutException(new Process([$this->superDistinctCommandName]), ProcessTimedOutException::TYPE_GENERAL);
        }
        parent::handleExecutorReturnCallback($cmd, $return);
    }
}
