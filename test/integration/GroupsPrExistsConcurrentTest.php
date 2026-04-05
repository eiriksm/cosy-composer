<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\Providers\NamedPrs;
use Github\Exception\ValidationFailedException;
use Violinist\Slug\Slug;

class GroupsPrExistsConcurrentTest extends ComposerUpdateIntegrationBase
{
    /** @var string */
    protected $composerAssetFiles = 'composer-group-contrib-and-core-concurrent';
    /** @var string */
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

    public function setUp(): void
    {
        parent::setUp();
        $this->mockProvider->method('updatePullRequest')
            ->willReturnCallback(function (Slug $slug, $number, array $params) {
            });
    }

    public function testGroupPrExistsCountsConcurrent(): void
    {
        $this->runtestExpectedOutput();
        // The first group (contrib) has an existing PR and throws ValidationFailedException.
        // With countPR called in the catch block, the concurrent limit (1) should be reached,
        // so the second group (core) should be skipped.
        $this->assertOutputContainsMessage(
            'Skipping Minor and Patch Core because the number of max concurrent PRs (1) seems to have been reached',
            $this->cosy
        );
    }

    /**
     * @param array<mixed> $cmd
     * @param mixed $return
     */
    public function handleExecutorReturnCallback(array $cmd, &$return): void
    {
        $command_parts = ['composer', 'update', 'drupal/core-composer-scaffold', 'drupal/core-project-message', 'drupal/core-recommended'];
        if (count(array_intersect($command_parts, $cmd)) === count($command_parts)) {
            $this->placeComposerLockContentsFromFixture('composer-group-contrib-and-core-concurrent.lock.updated_core', $this->dir);
        }
        $command_parts_contrib = ['composer', 'update', 'drupal/coffee', 'drupal/gin'];
        if (count(array_intersect($command_parts_contrib, $cmd)) === count($command_parts_contrib)) {
            $this->placeComposerLockContentsFromFixture('composer-group-contrib-and-core-concurrent.lock.updated_contrib', $this->dir);
        }
    }

    /**
     * @param array<mixed> $params
     * @return array<mixed>
     */
    protected function createPullRequest(Slug $slug, array $params)
    {
        throw new ValidationFailedException('The PR exists');
    }

    protected function getPrsNamed() : NamedPrs
    {
        return NamedPrs::createFromArray([
            'minor-patch-core' => [
                'title' => 'Update group `Minor and Patch Core`',
                'number' => 123,
                'head' => [
                    'ref' => 'minor-patch-core',
                ],
            ],
            'minor-and-patch-contrib' => [
                'title' => 'Update group `Minor and Patch Contrib`',
                'number' => 456,
                'head' => [
                    'ref' => 'minor-and-patch-contrib',
                ],
            ],
        ]);
    }
}
