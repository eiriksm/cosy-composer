<?php

namespace eiriksm\CosyComposerTest\integration\issues;

use eiriksm\CosyComposerTest\integration\ComposerUpdateIntegrationBase;

/**
 * Test for issue 164.
 */
class Issue164Test extends ComposerUpdateIntegrationBase
{
    protected ?string $packageForUpdateOutput = 'psr/log';
    protected ?string $packageVersionForFromUpdateOutput = '1.0.0';
    protected ?string $packageVersionForToUpdateOutput = '1.1.3';
    protected ?string $composerAssetFiles = 'composer164';

    public function testRequireDevAdded()
    {
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage(
            'Creating command composer require --dev -n --no-ansi psr/log:1.1.3 --update-with-dependencies',
            $this->cosy
        );
    }
}
