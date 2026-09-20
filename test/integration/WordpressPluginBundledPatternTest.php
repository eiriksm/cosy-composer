<?php

namespace eiriksm\CosyComposerTest\integration;

class WordpressPluginBundledPatternTest extends ComposerUpdateIntegrationBase
{
    protected ?string $composerAssetFiles = 'composer-wordpress-plugin-pattern';

    protected string $updateJson = '{
    "installed": [
        {
            "name": "wpackagist-plugin/akismet",
            "direct-dependency": true,
            "homepage": "https://wordpress.org/plugins/akismet",
            "source": "https://plugins.svn.wordpress.org/akismet",
            "version": "5.3",
            "latest": "5.3.1",
            "latest-status": "semver-safe-update",
            "description": "Used by millions, Akismet is quite possibly the best way in the world to protect your blog from spam.",
            "abandoned": false
        },
        {
            "name": "wpackagist-plugin/wordfence",
            "direct-dependency": true,
            "homepage": "https://wordpress.org/plugins/wordfence",
            "source": "https://plugins.svn.wordpress.org/wordfence",
            "version": "7.11.0",
            "latest": "7.11.1",
            "latest-status": "semver-safe-update",
            "description": "Wordfence Security - the most comprehensive WordPress security plugin.",
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
    private $targetPackages = ['wpackagist-plugin/akismet', 'wpackagist-plugin/wordfence'];

    public function testWildcardBundledPluginsProduceSingleUpdateCommand(): void
    {
        $this->runtestExpectedOutput();
        $matchingCommands = array_filter($this->composerUpdateCommands, function (array $command): bool {
            $matches = array_intersect($this->targetPackages, $command);
            return count($matches) === count($this->targetPackages);
        });
        self::assertCount(1, $this->composerUpdateCommands, 'Expected to run a single composer update command for bundled plugins');
        self::assertCount(1, $matchingCommands, 'Expected a single composer update command covering both target plugins');
        $command = reset($matchingCommands);
        foreach ($this->targetPackages as $package) {
            self::assertContains($package, $command);
        }
        self::assertCount(1, $this->prParamsArray, 'Expected a single pull request when bundling plugins by pattern');
        $body = $this->prParamsArray[0]['body'] ?? '';
        foreach ($this->targetPackages as $package) {
            $this->assertStringContainsString($package, $body);
        }
    }

    protected function handleExecutorReturnCallback(array $cmd, &$return): void
    {
        if (isset($cmd[0], $cmd[1]) && $cmd[0] === 'composer' && $cmd[1] === 'update') {
            if (array_intersect($this->targetPackages, $cmd)) {
                $this->composerUpdateCommands[] = $cmd;
                $this->placeComposerLockContentsFromFixture('composer-wordpress-plugin-pattern.lock.updated', $this->dir);
            }
        }
    }
}
