<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\Exceptions\OutsideProcessingHoursException;

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
        $this->expectException(OutsideProcessingHoursException::class);
        $this->runtestExpectedOutput();
    }

    public function testTimeFrameWrongFormat()
    {
        $this->composerAssetFiles = 'composer.timeframe_wrong';
        $this->createComposerFileFromFixtures($this->dir, sprintf('%s.json', $this->composerAssetFiles));
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The timeframe should consist of two 24 hour format times separated by a dash ("-")');
        $this->runtestExpectedOutput();
    }

    public function testTimeFrameNotOutside()
    {
        $this->composerAssetFiles = 'composer.timeframe_allowed';
        $this->createComposerFileFromFixtures($this->dir, sprintf('%s.json', $this->composerAssetFiles));
        $this->runtestExpectedOutput();
    }
}
