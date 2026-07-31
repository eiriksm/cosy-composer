<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test for labels on sec only, but no sec updates.
 */
class LabelsNoUpdateTest extends LabelTestBase
{
    protected ?string $composerAssetFiles = 'composer.labels_no_sec_updates';
    protected $hasUpdatedPsrLog = false;
    protected $hasUpdatedPsrCache = false;
    protected ?string $packageForUpdateOutput = 'psr/log';
    protected ?string $packageVersionForFromUpdateOutput = '1.1.3';
    protected ?string $packageVersionForToUpdateOutput = '1.1.4';
    protected $checkPrUrl = true;
}
