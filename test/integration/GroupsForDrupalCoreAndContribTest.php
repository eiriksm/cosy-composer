<?php

namespace eiriksm\CosyComposerTest\integration;

class GroupsForDrupalCoreAndContribTest extends ComposerUpdateIntegrationBase
{
    protected $composerAssetFiles = 'composer-group-contrib-and-core';
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
            "name": "drupal/core-composer-scaffold",
            "direct-dependency": true,
            "homepage": "https://www.drupal.org/project/drupal",
            "source": "https://github.com/drupal/core-composer-scaffold/tree/11.1.4",
            "version": "11.1.4",
            "latest": "11.1.5",
            "latest-status": "semver-safe-update",
            "description": "A flexible Composer project scaffold builder.",
            "abandoned": false
        },
        {
            "name": "drupal/core-project-message",
            "direct-dependency": true,
            "homepage": "https://www.drupal.org/project/drupal",
            "source": "https://github.com/drupal/core-project-message/tree/11.1.4",
            "version": "11.1.4",
            "latest": "11.1.5",
            "latest-status": "semver-safe-update",
            "description": "Adds a message after Composer installation.",
            "abandoned": false
        },
        {
            "name": "drupal/core-recommended",
            "direct-dependency": true,
            "homepage": null,
            "source": "https://github.com/drupal/core-recommended/tree/11.1.4",
            "version": "11.1.4",
            "latest": "11.1.5",
            "latest-status": "semver-safe-update",
            "description": "Core and its dependencies with known-compatible minor versions. Require this project INSTEAD OF drupal/core.",
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

    public function testUpdatesGroupedContribAndCore()
    {
        $this->runtestExpectedOutput();
        $output = $this->cosy->getOutput();
        self::assertEquals($this->prParamsArray[0]["head"], 'minor-and-patch-contrib');
        self::assertEquals($this->prParamsArray[1]["head"], 'minor-patch-core');
    }

    public function handleExecutorReturnCallback(array $cmd, &$return)
    {
        // If it contains all of these items it seems to be the contrib update
        // command.
        $command_parts = ['composer', 'update', 'drupal/coffee', 'drupal/gin'];
        if (count(array_intersect($command_parts, $cmd)) === count($command_parts)) {
            $this->placeComposerLockContentsFromFixture('composer-group-contrib-and-core.lock.updated_contrib', $this->dir);
        }
        // If its the core thing, let's do that.
        $command_parts_for_core = ['composer', 'update', 'drupal/core-project-message', 'drupal/core-recommended'];
        if (count(array_intersect($command_parts_for_core, $cmd)) === count($command_parts_for_core)) {
            $this->placeComposerLockContentsFromFixture('composer-group-contrib-and-core.lock.updated_core', $this->dir);
        }
    }
}
