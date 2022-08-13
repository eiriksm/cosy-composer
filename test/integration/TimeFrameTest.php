<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test for branch prefix with one_per option set.
 */
class TimeFrameTest extends ComposerUpdateIntegrationBase
{
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.1.4';
    protected $composerAssetFiles = 'composer.timeframe';

    public function testTimeFrame()
    {
        self::expectException(eiriksm\CosyComposer\Exceptions\OutsideProcessingHoursException::class);
        $this->runtestExpectedOutput();
    }
}
