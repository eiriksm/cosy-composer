<?php

namespace eiriksm\CosyComposerTest\integration;

class UpdateIndirectButHasDirectTest extends ComposerUpdateIntegrationBase
{
    protected ?string $packageForUpdateOutput = 'symfony/polyfill-mbstring';
    protected ?string $packageVersionForFromUpdateOutput = 'v1.23.0';
    protected ?string $packageVersionForToUpdateOutput = 'v1.24.0';
    protected ?string $composerAssetFiles = 'composer.indirect.direct';
    protected $usesDirect = false;
    protected $checkPrUrl = true;

    public function testUpdateIndirect()
    {
        $this->runtestExpectedOutput();
        self::assertEquals('Update symfony/polyfill-mbstring from v1.23.0 to v1.24.0', $this->prParams["title"]);
        $output = $this->cosy->getOutput();
        $found_msg = false;
        foreach ($output as $item) {
            if ($item->getType() !== 'message') {
                continue;
            }
            if ($item->getMessage() !== 'Checking out new branch: symfonypolyfillmbstringv1230v1240') {
                continue;
            }
            $found_msg = true;
        }
        self::assertEquals(true, $found_msg);
    }
}
