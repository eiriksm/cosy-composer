<?php

namespace eiriksm\CosyComposerTest\integration\issues;

use eiriksm\CosyComposer\CosyComposer;
use eiriksm\CosyComposerTest\integration\ComposerUpdateIntegrationBase;

/**
 * Test for issue 200.
 */
class Issue200Test extends ComposerUpdateIntegrationBase
{
    protected $packageForUpdateOutput = 'fzaninotto/faker';
    protected $packageVersionForFromUpdateOutput = 'v1.9.2';
    protected $packageVersionForToUpdateOutput = 'v.1.9.2';
    protected $composerAssetFiles = 'composer164';

    protected function createUpdateJsonFromData($package, $version, $new_version)
    {
        return sprintf('{"installed": [{"name": "%s", "version": "%s", "latest": "%s", "latest-status": "up-to-date"}]}', $package, $version, $new_version);
    }

    public function testRequireDevAdded()
    {
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage('No updates found', $this->cosy);
    }
}
