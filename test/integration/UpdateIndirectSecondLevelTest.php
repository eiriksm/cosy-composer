<?php

namespace eiriksm\CosyComposerTest\integration;

class UpdateIndirectSecondLevelTest extends ComposerUpdateIntegrationBase
{
    protected $packageForUpdateOutput = 'psr/container';
    protected $packageVersionForFromUpdateOutput = '1.1.1';
    protected $packageVersionForToUpdateOutput = '1.1.2';
    protected $composerAssetFiles = 'composer.indirect.second';
    protected $usesDirect = false;
    protected $checkPrUrl = true;

    public function testUpdateIndirectSecond()
    {
        $this->runtestExpectedOutput();
        self::assertEquals('Update dependencies of psy/psysh', $this->prParams["title"]);
    }

    protected function createExpectedCommandForPackage($package)
    {
        // We are actually updating the required package which depends on this one.
        return ['composer', 'update', '-n', '--no-ansi', 'psy/psysh', '--with-dependencies'];
    }
}
