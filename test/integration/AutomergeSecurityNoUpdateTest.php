<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test for automerge being enabled for security, but no security updates.
 */
class AutomergeSecurityNoUpdateTest extends AutoMergeBase
{
    protected ?string $composerAssetFiles = 'composer.automerge_sec_no_update';
    protected $hasUpdatedPsrLog = false;
    protected $hasUpdatedPsrCache = false;
    protected ?string $packageForUpdateOutput = 'psr/log';
    protected ?string $packageVersionForFromUpdateOutput = '1.1.3';
    protected ?string $packageVersionForToUpdateOutput = '1.1.4';
    protected $hasAutoMerge = false;
    protected $checkPrUrl = true;
}
