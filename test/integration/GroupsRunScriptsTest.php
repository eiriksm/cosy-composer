<?php

namespace eiriksm\CosyComposerTest\integration;

class GroupsRunScriptsTest extends ComposerUpdateIntegrationBase
{
    /** @var string */
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

    protected ?string $composerAssetFiles = 'composer-groups-run-scripts';

    /** @var bool */
    private $noScriptsUsed = false;

    public function testRuleOverridesGlobalRunScripts(): void
    {
        $this->runtestExpectedOutput();
        self::assertTrue($this->noScriptsUsed, 'Rule config run_scripts=0 should override global value of 1');
    }

    /**
     * @param array<string> $cmd
     * @param int $return
     */
    public function handleExecutorReturnCallback(array $cmd, &$return): void
    {
        if (in_array('--no-scripts', $cmd, true)) {
            $this->noScriptsUsed = true;
        }
        $command_parts = ['composer', 'update', 'psr/log', 'psr/cache'];
        if (count(array_intersect($command_parts, $cmd)) === count($command_parts)) {
            $this->placeComposerLockContentsFromFixture('composer.tg.lock.updated', $this->dir);
        }
    }
}
