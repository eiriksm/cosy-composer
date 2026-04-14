<?php

namespace eiriksm\CosyComposerTest\integration;

class GroupsUpdateWithDependenciesTest extends ComposerUpdateIntegrationBase
{
    protected $composerAssetFiles = 'composer-groups-update-with-dependencies';
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
}';

    private $withDependenciesUsed = false;

    public function testRuleOverridesGlobalUpdateWithDependencies()
    {
        $this->runtestExpectedOutput();
        self::assertFalse($this->withDependenciesUsed, 'Rule config update_with_dependencies=0 should override global value of 1');
    }

    protected function handleExecutorReturnCallback(array $cmd, &$return)
    {
        if (in_array('--with-dependencies', $cmd, true)) {
            $this->withDependenciesUsed = true;
        }
        $command_parts = ['composer', 'update', 'psr/log', 'psr/cache'];
        if (count(array_intersect($command_parts, $cmd)) === count($command_parts)) {
            $this->placeComposerLockContentsFromFixture('composer.tg.lock.updated', $this->dir);
        }
    }
}
