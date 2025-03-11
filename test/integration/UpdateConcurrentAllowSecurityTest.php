<?php

namespace eiriksm\CosyComposerTest\integration;

use Violinist\SymfonyCloudSecurityChecker\SecurityChecker;

class UpdateConcurrentAllowSecurityTest extends ComposerUpdateIntegrationBase
{
    protected $composerAssetFiles = 'composer.concurrent.security';
    protected $packageForUpdateOutput = 'psr/http-factory';

    public function setUp(): void
    {
        parent::setUp();
        $this->updateJson = '{"installed": [{"name": "psr/http-factory", "version": "1.0.2", "latest": "1.1.0", "latest-status": "semver-safe-update"},{"name": "drupal/core", "version": "10.2.10", "latest": "10.3.10", "latest-status": "semver-safe-update"},{"name": "drupal/core-recommended", "version": "10.2.10", "latest": "10.3.10", "latest-status": "semver-safe-update"}]}';
        $checker = $this->createMock(SecurityChecker::class);
        $checker->method('checkDirectory')
            ->willReturn([
                'drupal/core' => true,
                'drupal/core-recommended' => true,
            ]);
        $this->cosy->getCheckerFactory()->setChecker($checker);
    }

    public function testUpdatesSecurityBeyondConcurrent()
    {
        $this->runtestExpectedOutput();
        self::assertFalse($this->findMessage('Skipping drupal/core because the number of max concurrent PRs (1) seems to have been reached', $this->cosy));
        $this->assertOutputContainsMessage('The concurrent limit (1) is reached, but the update of drupal/core-recommended is a security update, so we will try to update it anyway.', $this->cosy);
    }

    protected function getPrsNamed()
    {
        return [
            'psrhttpfactory102110' => [
                'base' => [
                    'sha' => 'abab',
                ],
                'number' => 123,
                'title' => 'Update psr/http-factory from 1.0.2 to 1.1.0',
            ],
        ];
    }

    protected function getBranchesFlattened()
    {
        return [
            'psrhttpfactory102110',
        ];
    }
}
