<?php

namespace eiriksm\CosyComposerTest\integration\issues;

use eiriksm\CosyComposerTest\integration\ComposerUpdateIntegrationBase;

/**
 * Test for issue 238.
 */
class Issue238Test extends ComposerUpdateIntegrationBase
{
    protected ?string $packageForUpdateOutput = 'psr/log';
    protected ?string $packageVersionForFromUpdateOutput = '1.1.3';
    protected ?string $packageVersionForToUpdateOutput = '1.1.4';
    protected ?string $composerAssetFiles = 'composer-238';

    public function testDevNameNotFail()
    {
        $this->runtestExpectedOutput();
    }
}
