<?php

namespace eiriksm\CosyComposerTest\integration;

class UpdateIndirectButHasDirectTest extends ComposerUpdateIntegrationBase
{
    protected $packageForUpdateOutput = 'symfony/polyfill-mbstring';
    protected $packageVersionForFromUpdateOutput = 'v1.23.0';
    protected $packageVersionForToUpdateOutput = 'v1.24.0';
    protected $composerAssetFiles = 'composer.indirect.direct';
    protected $usesDirect = false;
    protected $checkPrUrl = true;

    public function testUpdateIndirect()
    {
        $this->runtestExpectedOutput();
        self::assertEquals('Update symfony/polyfill-mbstring from v1.23.0 to v1.24.0', $this->prParams["title"]);
        self::assertEquals('symfonypolyfillmbstringv1230v1240', $this->prParams["head"]);
    }
}
