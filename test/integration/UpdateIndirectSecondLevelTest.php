<?php

namespace eiriksm\CosyComposerTest\integration;

class UpdateIndirectSecondLevelTest extends ComposerUpdateIntegrationBase
{
    protected ?string $packageForUpdateOutput = 'psr/container';
    protected ?string $packageVersionForFromUpdateOutput = '1.1.1';
    protected ?string $packageVersionForToUpdateOutput = '1.1.2';
    protected ?string $composerAssetFiles = 'composer.indirect.second';
    protected $usesDirect = false;
    protected $checkPrUrl = true;

    public function testUpdateIndirectSecond()
    {
        $this->runtestExpectedOutput();
        self::assertEquals('Update dependencies of psy/psysh', $this->prParams["title"]);
    }

    /**
     * @return array<int, string>
     */
    protected function createExpectedCommandForPackage(string $package) : array
    {
        // We are actually updating the required package which depends on this one.
        return ['composer', 'update', '-n', '--no-ansi', 'psy/psysh', '--with-dependencies'];
    }
}
