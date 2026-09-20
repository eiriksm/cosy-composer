<?php

namespace eiriksm\CosyComposerTest\integration\issues;

use eiriksm\CosyComposerTest\integration\ComposerUpdateIntegrationBase;

/**
 * Test for issue 200.
 */
class Issue200Test extends ComposerUpdateIntegrationBase
{
    protected ?string $packageForUpdateOutput = 'fzaninotto/faker';
    protected ?string $packageVersionForFromUpdateOutput = 'v1.9.2';
    protected ?string $packageVersionForToUpdateOutput = 'v.1.9.2';
    protected ?string $composerAssetFiles = 'composer164';

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
