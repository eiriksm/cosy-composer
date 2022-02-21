<?php

namespace eiriksm\CosyComposerTest\integration;

class UpdateIndirectTest extends ComposerUpdateIntegrationBase
{
    protected $packageForUpdateOutput = 'symfony/polyfill-mbstring';
    protected $packageVersionForFromUpdateOutput = 'v1.23.0';
    protected $packageVersionForToUpdateOutput = 'v1.24.0';
    protected $composerAssetFiles = 'composer.indirect';
    protected $usesDirect = false;
    protected $checkPrUrl = true;

    public function testUpdateIndirect()
    {
        $this->runtestExpectedOutput();
        self::assertEquals('Update dependencies of symfony/var-dumper', $this->prParams["title"]);
    }

    protected function createExpectedCommandForPackage($package)
    {
        // We are actually updating the required package which depends on this one.
        return 'composer update -n --no-ansi symfony/var-dumper --with-dependencies ';
    }
}
