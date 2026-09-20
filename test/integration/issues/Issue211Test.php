<?php

namespace eiriksm\CosyComposerTest\integration\issues;

use eiriksm\CosyComposerTest\integration\ComposerUpdateIntegrationBase;

/**
 * Test for issue 211.
 */
class Issue211Test extends ComposerUpdateIntegrationBase
{
    protected ?string $packageForUpdateOutput = 'psr/log';
    protected ?string $packageVersionForFromUpdateOutput = '1.0.0';
    protected ?string $packageVersionForToUpdateOutput = '1.1.3';
    protected ?string $composerAssetFiles = 'composer-no-lock';
    protected $checkPrUrl = true;

    public function testLockDataNotFailed()
    {
        $this->runtestExpectedOutput();
        self::assertEquals('psrlog100113', $this->prParams["head"]);
    }

    /**
     * @return array<int, string>
     */
    protected function createExpectedCommandForPackage(string $package) : array
    {
        return ['composer', 'require', '--dev', '-n', '--no-ansi', "$package:1.1.3", '--update-with-dependencies'];
    }

    protected function handleExecutorReturnCallback($cmd, &$return)
    {
        if ($cmd === ['composer', 'install', '--no-ansi', '-n']) {
            $this->placeComposerLockContentsFromFixture('composer164.lock', $this->dir);
        }
    }
}
