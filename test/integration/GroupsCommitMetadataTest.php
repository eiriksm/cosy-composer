<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\Helpers;
use Symfony\Component\Yaml\Yaml;

class GroupsCommitMetadataTest extends ComposerUpdateIntegrationBase
{
    private $commitCommand = '';
    protected $composerAssetFiles = 'composer.tg';
    protected $updateJson = '{
    "installed": [
        {
            "name": "psr/log",
            "direct-dependency": true,
            "version": "1.1.3",
            "latest": "1.1.4",
            "latest-status": "semver-safe-update",
            "abandoned": false
        },
        {
            "name": "psr/cache",
            "direct-dependency": true,
            "version": "1.0.0",
            "latest": "1.0.1",
            "latest-status": "semver-safe-update",
            "abandoned": false
        }
    ]
}
';

    public function testGroupCommitMetadata()
    {
        $this->runtestExpectedOutput();
        $parts = explode(Helpers::getCommitMessageSeparator(), $this->commitCommand);
        self::assertCount(2, $parts, 'Commit message should have metadata separator');
        $data = Yaml::parse($parts[1]);
        self::assertNotEmpty($data['violinist_metadata']);
        self::assertEquals('group', $data['violinist_metadata']['type']);
        self::assertNotEmpty($data['update_data']);
        self::assertIsArray($data['update_data']);
        // Verify that each package has the correct metadata.
        $packages = array_column($data['update_data'], 'package');
        self::assertContains('psr/log', $packages);
        self::assertContains('psr/cache', $packages);
        foreach ($data['update_data'] as $update) {
            self::assertArrayHasKey('package', $update);
            self::assertArrayHasKey('from', $update);
            self::assertArrayHasKey('to', $update);
        }
    }

    public function handleExecutorReturnCallback(array $cmd, &$return)
    {
        $command_parts = ['composer', 'update', 'psr/log', 'psr/cache'];
        if (count(array_intersect($command_parts, $cmd)) === count($command_parts)) {
            $this->placeComposerLockContentsFromFixture('composer.tg.lock.updated', $this->dir);
        }
        $cmd_string = implode(' ', $cmd);
        if (strpos($cmd_string, 'git commit') !== false) {
            $this->commitCommand = $cmd_string;
        }
    }
}
