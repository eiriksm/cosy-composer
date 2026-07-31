<?php

namespace eiriksm\CosyComposerTest\integration;

class UpdateIndirectTest extends ComposerUpdateIntegrationBase
{
    protected ?string $packageForUpdateOutput = 'symfony/polyfill-mbstring';
    protected ?string $packageVersionForFromUpdateOutput = 'v1.23.0';
    protected ?string $packageVersionForToUpdateOutput = 'v1.24.0';
    protected ?string $composerAssetFiles = 'composer.indirect';
    protected $usesDirect = false;
    protected $checkPrUrl = true;

    public function testUpdateIndirect()
    {
        $this->runtestExpectedOutput();
        self::assertEquals('Update dependencies of symfony/var-dumper', $this->prParams["title"]);
    }

    /**
     * @return array<int, string>
     */
    protected function createExpectedCommandForPackage(string $package) : array
    {
        // We are actually updating the required package which depends on this one.
        return ['composer', 'update', '-n', '--no-ansi', 'symfony/var-dumper', '--with-dependencies'];
    }
}
