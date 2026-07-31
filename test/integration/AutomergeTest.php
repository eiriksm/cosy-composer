<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test for automerge being enabled.
 */
class AutomergeTest extends AutoMergeBase
{
    protected ?string $composerAssetFiles = 'composer.automerge';
    protected $hasUpdatedPsrLog = false;
    protected $hasUpdatedPsrCache = false;
    protected ?string $packageForUpdateOutput = 'psr/log';
    protected ?string $packageVersionForFromUpdateOutput = '1.1.3';
    protected ?string $packageVersionForToUpdateOutput = '1.1.4';
    protected $hasAutoMerge = true;
    protected $checkPrUrl = true;
}
