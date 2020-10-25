<?php

namespace eiriksm\CosyComposerTest\integration\issues;

use eiriksm\ArrayOutput\ArrayOutput;
use eiriksm\CosyComposerTest\integration\ComposerUpdateIntegrationBase;

class Issue145Test extends ComposerUpdateIntegrationBase
{
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.1.3';
    protected $composerAssetFiles = 'composer145';
    private $numberOfIstalls = 0;

    public function testIssue145()
    {
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage('Creating pull request from psrsimplecache100101', $this->cosy);
        $this->assertOutputContainsMessage('Creating pull request from psrlog100113', $this->cosy);
    }

    protected function createUpdateJsonFromData($package, $version, $new_version)
    {
        return sprintf('{"installed": [{"name": "psr/simple-cache", "version": "1.0.0", "latest": "1.0.1", "latest-status": "semver-safe-update"}, {"name": "%s", "version": "%s", "latest": "%s", "latest-status": "semver-safe-update"}]}', $package, $version, $new_version);
    }

    protected function handleExecutorReturnCallback($cmd, &$return)
    {
        switch ($cmd) {
            case 'composer require -n --no-ansi psr/simple-cache:1.0.1 --update-with-dependencies ':
                $this->placeUpdatedComposerLock();
                break;

            case 'composer require -n --no-ansi psr/log:1.1.3 --update-with-dependencies ':
                $this->placeUpdatedComposerLock();
                break;

            case 'git checkout .':
                $this->placeInitialComposerLock();
                break;

            case 'composer install --no-ansi -n':
                $this->numberOfIstalls++;
                if ($this->numberOfIstalls === 3) {
                    $return = 1;
                }
                break;
        }
    }
}
