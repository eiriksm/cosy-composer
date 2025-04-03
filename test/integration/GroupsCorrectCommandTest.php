<?php

namespace eiriksm\CosyComposerTest\integration;

class GroupsCorrectCommandTest extends ComposerUpdateIntegrationBase
{
    private $correctCommand = false;
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

    public function testGroupAllPackages()
    {
        $this->runtestExpectedOutput();
        $output = $this->cosy->getOutput();
        self::assertEquals(true, $this->correctCommand);
    }

    public function handleExecutorReturnCallback(array $cmd, &$return)
    {
        // If it contains all of these items it seems to be the contrib update
        // command.
        $command_parts = ['composer', 'update', 'psr/log', 'psr/cache'];
        if (count(array_intersect($command_parts, $cmd)) === count($command_parts)) {
            $this->correctCommand = true;
        }
    }
}
