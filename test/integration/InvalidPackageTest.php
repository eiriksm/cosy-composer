<?php

namespace eiriksm\CosyComposerTest\integration;

class InvalidPackageTest extends ComposerUpdateIntegrationBase
{
    protected ?string $composerAssetFiles = 'composer-psr-log';
    protected ?string $packageVersionForFromUpdateOutput = '1.0.0';
    protected ?string $packageVersionForToUpdateOutput = '1.0.1';
    protected ?string $packageForUpdateOutput = 'eiriksm/fake-package';

    public function testUpdatesFoundButInvalidPackage()
    {
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage('Caught an exception: Did not find the requested package (eiriksm/fake-package) in the lockfile. This is probably an error', $this->cosy);
    }
}
