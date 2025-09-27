<?php

namespace eiriksm\CosyComposerTest\integration;

class BundledPackagesPatternTest extends ComposerUpdateIntegrationBase
{
    protected $composerAssetFiles = 'composer-bundled-pattern';

    protected $updateJson = '{
    "installed": [
        {
            "name": "drupal/coffee",
            "direct-dependency": true,
            "homepage": "https://drupal.org/project/coffee",
            "source": "https://git.drupalcode.org/project/coffee",
            "version": "2.0.0",
            "latest": "2.0.1",
            "latest-status": "semver-safe-update",
            "description": "Provides an Alfred like search box to navigate within your site.",
            "abandoned": false
        },
        {
            "name": "drupal/gin",
            "direct-dependency": true,
            "homepage": "https://www.drupal.org/project/gin",
            "source": "https://git.drupalcode.org/project/gin",
            "version": "4.0.5",
            "latest": "4.0.6",
            "latest-status": "semver-safe-update",
            "description": "For a better Admin and Content Editor Experience.",
            "abandoned": false
        }
    ]
}
';

    /**
     * @var array<int, array<int, string>>
     */
    private $composerUpdateCommands = [];

    /**
     * @var string[]
     */
    private $targetPackages = ['drupal/coffee', 'drupal/gin'];

    public function testWildcardBundledPackagesProduceSingleUpdateCommand()
    {
        $this->runtestExpectedOutput();
        $matchingCommands = array_filter($this->composerUpdateCommands, function (array $command): bool {
            $matches = array_intersect($this->targetPackages, $command);
            return count($matches) === count($this->targetPackages);
        });
        self::assertCount(1, $this->composerUpdateCommands, 'Expected to run a single composer update command for bundled packages');
        self::assertCount(1, $matchingCommands, 'Expected a single composer update command covering both target packages');
        $command = reset($matchingCommands);
        foreach ($this->targetPackages as $package) {
            self::assertContains($package, $command);
        }
        self::assertCount(1, $this->prParamsArray, 'Expected a single pull request when bundling packages by pattern');
        $body = $this->prParamsArray[0]['body'] ?? '';
        foreach ($this->targetPackages as $package) {
            $this->assertStringContainsString($package, $body);
        }
    }

    protected function handleExecutorReturnCallback(array $cmd, &$return)
    {
        if (isset($cmd[0], $cmd[1]) && $cmd[0] === 'composer' && $cmd[1] === 'update') {
            if (array_intersect($this->targetPackages, $cmd)) {
                $this->composerUpdateCommands[] = $cmd;
                $this->placeComposerLockContentsFromFixture('composer-bundled-pattern.lock.updated', $this->dir);
            }
        }
    }
}
