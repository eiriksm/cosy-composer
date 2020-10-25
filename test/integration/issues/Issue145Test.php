<?php

namespace eiriksm\CosyComposerTest\integration\issues;

use eiriksm\ArrayOutput\ArrayOutput;
use eiriksm\CosyComposerTest\integration\ComposerUpdateIntegrationBase;

class Issue145Test extends ComposerUpdateIntegrationBase
{
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = 'dev-master 2b71ffb';
    protected $packageVersionForToUpdateOutput = 'dev-master dd738d0';
    protected $composerAssetFiles = 'composer-dev-master';

    public function testIssue145()
    {
        $this->runtestExpectedOutput();

        $out = $this->cosy->getOutput();
        $a = 'b';
    }

    protected function createUpdateJsonFromData($package, $version, $new_version)
    {
        return sprintf('{"installed": [{"name": "eiriksm/cosy-composer", "version": "1.0.0", "latest": "1.2.3", "latest-status": "semver-safe-update"}, {"name": "%s", "version": "%s", "latest": "%s", "latest-status": "semver-safe-update"}]}', $package, $version, $new_version);
    }
}
