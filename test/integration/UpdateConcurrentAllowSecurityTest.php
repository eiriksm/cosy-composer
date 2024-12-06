<?php

namespace eiriksm\CosyComposerTest\integration;

class UpdateConcurrentAllowSecurityTest extends ComposerUpdateIntegrationBase
{
    protected $composerAssetFiles = 'composer.concurrent.security';
    protected $packageForUpdateOutput = 'psr/http-factory';

    public function setUp(): void
    {
        parent::setUp();
        $this->updateJson = '{"installed": [{"name": "psr/http-factory", "version": "1.0.2", "latest": "1.1.0", "latest-status": "semver-safe-update"},{"name": "drupal/core", "version": "10.2.10", "latest": "10.3.10", "latest-status": "semver-safe-update"},{"name": "drupal/core-recommended", "version": "10.2.10", "latest": "10.3.10", "latest-status": "semver-safe-update"}]}';
    }

    public function testUpdatesSecurityBeyondConcurrent()
    {
        $this->runtestExpectedOutput();
        self::assertFalse($this->findMessage('Skipping drupal/core because the number of max concurrent PRs (1) seems to have been reached', $this->cosy));
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
