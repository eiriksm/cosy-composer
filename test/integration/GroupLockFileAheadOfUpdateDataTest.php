<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test that a group is not reported as not updated if the lock file was ahead.
 */
class GroupLockFileAheadOfUpdateDataTest extends ComposerUpdateIntegrationBase
{
    protected ?string $composerAssetFiles = 'composer-group-contrib-and-core';
    /**
     * @var string
     */
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

    public function testGroupLockFileAheadOfUpdateData() : void
    {
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage('Skipping Minor and Patch Contrib since the lock file did not match the data the update check was based on: drupal/coffee (expected 2.0.0, found 2.0.1), drupal/gin (expected 4.0.5, found 4.0.6)', $this->cosy);
        self::assertFalse($this->findMessage('Package drupal/coffee was not updated', $this->cosy));
        self::assertFalse($this->findMessage('Package drupal/gin was not updated', $this->cosy));
    }

    public function handleExecutorReturnCallback(array $cmd, &$return) : void
    {
        if ($cmd == ['git', 'checkout', '-b', 'minor-and-patch-contrib']) {
            // Simulate the lock file already having the contrib updates when we
            // are about to run them.
            $this->placeComposerLockContentsFromFixture('composer-group-contrib-and-core.lock.updated_contrib', $this->dir);
        }
    }
}
