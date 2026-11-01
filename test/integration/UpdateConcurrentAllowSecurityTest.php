<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\Providers\NamedPrs;
use Violinist\SymfonyCloudSecurityChecker\SecurityChecker;

class UpdateConcurrentAllowSecurityTest extends ComposerUpdateIntegrationBase
{
    protected ?string $composerAssetFiles = 'composer.concurrent.security';
    protected ?string $packageForUpdateOutput = 'psr/http-factory';

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

    public function testSecurityBypassTakesPrecedenceOverPackageBypass(): void
    {
        // drupal/core-recommended is both a security update (per the mocked
        // checker in setUp) and configured to bypass the concurrent limit
        // here. The security bypass is checked first, so its message should
        // be logged, and the package-bypass branch should never be reached
        // for this package.
        $this->setConcurrentUpdatesBypassPackages(['drupal/core-recommended']);
        $this->runtestExpectedOutput();

        $this->assertOutputContainsMessage(
            'The concurrent limit (1) is reached, but the update of drupal/core-recommended is a security update, so we will try to update it anyway.',
            $this->cosy
        );
        self::assertFalse($this->findMessage(
            'The concurrent limit (1) is reached, but drupal/core-recommended is configured to bypass the concurrent limit, so we will try to update it anyway.',
            $this->cosy
        ));
    }

    /**
     * @param array<int, string> $packages
     */
    private function setConcurrentUpdatesBypassPackages(array $packages): void
    {
        $composer_file = sprintf('%s/composer.json', $this->dir);
        $composer_data = json_decode(file_get_contents($composer_file));
        $composer_data->extra->violinist->concurrent_updates_bypass_packages = $packages;
        file_put_contents(
            $composer_file,
            json_encode($composer_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    protected function getPrsNamed() : NamedPrs
    {
        return NamedPrs::createFromArray([
            'psrhttpfactory102110' => [
                'base' => [
                    'sha' => 'abab',
                ],
                'number' => 123,
                'title' => 'Update psr/http-factory from 1.0.2 to 1.1.0',
                'head' => [
                    'ref' => 'psrhttpfactory102110',
                ],
            ],
        ]);
    }

    protected function getBranchesFlattened()
    {
        return [
            'psrhttpfactory102110',
        ];
    }
}
