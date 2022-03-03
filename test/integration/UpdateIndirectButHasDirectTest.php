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
        $output = $this->cosy->getOutput();
        $found_msg = false;
        foreach ($output as $item) {
            if ($item->getType() !== 'update') {
                continue;
            }
            $context = $item->getContext();
            foreach ($context['packages'] as $package) {
                if (empty($package->name) || $package->name !== 'symfony/polyfill-mbstring') {
                    continue;
                }
                if (empty($package->latest) || $package->latest !== 'v1.24.0') {
                    continue;
                }
                $found_msg = true;
            }
        }
        self::assertEquals(true, $found_msg);
    }
}
