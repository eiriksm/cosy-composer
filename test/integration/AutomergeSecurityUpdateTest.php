<?php

namespace eiriksm\CosyComposerTest\integration;

use Violinist\SymfonyCloudSecurityChecker\SecurityChecker;

/**
 * Test for automerge being enabled for security, but no security updates.
 */
class AutomergeSecurityUpdateTest extends ComposerUpdateIntegrationBase
{
    protected $composerAssetFiles = 'composer.automerge_sec_update';
    protected $hasUpdatedPsrLog = false;
    protected $hasUpdatedPsrCache = false;
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.1.3';
    protected $packageVersionForToUpdateOutput = '1.1.4';
    protected $hasAutoMerge = true;
    protected $checkPrUrl = true;

    public function setUp()
    {
        parent::setUp();
        $checker = $this->createMock(SecurityChecker::class);
        $checker->method('checkDirectory')
            ->willReturn([
                'psr/log' => true,
            ]);
        $this->cosy->getCheckerFactory()->setChecker($checker);
    }

    public function testAutomerge()
    {
        $this->runtestExpectedOutput();
    }
}
