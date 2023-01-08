<?php

namespace eiriksm\CosyComposerTest\integration;

use Github\Exception\ValidationFailedException;
use Violinist\Slug\Slug;

/**
 * Test for labels on sec only, but no sec updates.
 */
class LabelsTest extends LabelTestBase
{
    protected $composerAssetFiles = 'composer.labels';
    protected $hasUpdatedPsrLog = false;
    protected $hasUpdatedPsrCache = false;
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.1.3';
    protected $packageVersionForToUpdateOutput = '1.1.4';
    protected $checkPrUrl = true;

    protected $expectedLabelAdding = true;

    /**
     * @dataProvider getUpdateVariations
     */
    public function testLabels($should_have_updated)
    {
        parent::testLabels($should_have_updated);
        self::assertEquals(['my label over here'], $this->labelsAdded);
    }

}
