<?php

namespace eiriksm\CosyComposerTest\integration;

class InvalidPackageTest extends ComposerUpdateIntegrationBase
{
    protected $composerAssetFiles = 'composer-psr-log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.0.1';
    protected $packageForUpdateOutput = 'eiriksm/fake-package';

    public function testUpdatesFoundButInvalidPackage()
    {
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage('Caught an exception: Did not find the requested package (eiriksm/fake-package) in the lockfile. This is probably an error', $this->cosy);
    }
}
