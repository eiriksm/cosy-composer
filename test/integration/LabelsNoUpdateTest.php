<?php

namespace eiriksm\CosyComposerTest\integration;

use Github\Exception\ValidationFailedException;
use Violinist\Slug\Slug;

/**
 * Test for labels on sec only, but no sec updates.
 */
class LabelsNoUpdateTest extends LabelTestBase
{
    protected $composerAssetFiles = 'composer.labels_no_sec_updates';
    protected $hasUpdatedPsrLog = false;
    protected $hasUpdatedPsrCache = false;
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.1.3';
    protected $packageVersionForToUpdateOutput = '1.1.4';
    protected $checkPrUrl = true;

}
