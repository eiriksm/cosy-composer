<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Tests that a group rule can allow updates beyond the constraint.
 *
 * The composer.json fixture pins drupal/coffee and drupal/gin to exact versions
 * (2.0.0 and 4.0.5) and disables allow_updates_beyond_constraint globally. The
 * "Minor and Patch Contrib" rule re-enables allow_updates_beyond_constraint
 * through its own config, so the grouped contrib packages should be updated with
 * a "composer require" that bumps them beyond their (exact) constraint to the
 * latest available version. The core group does not enable it (and stays inside
 * its constraint), so it is still updated with a regular "composer update".
 *
 * The contrib group also contains drupal/pathauto, which can reach its latest
 * version without going beyond its ">=1.13" constraint. It still has to be part
 * of the require command for composer to update it, but with the constraint it
 * already has, so that we do not narrow it down to an exact version.
 */
class GroupAllowUpdatesBeyondConstraintTest extends ComposerUpdateIntegrationBase
{
    protected ?string $composerAssetFiles = 'composer-group-contrib-and-core-beyond';

    private bool $foundContribRequire = false;

    private bool $foundCoreUpdate = false;

    /**
     * @var string[]
     */
    private array $contribRequireCommand = [];

    /**
     * @var string
     */
    protected $updateJson = '{
    "installed": [
        {
            "name": "drupal/coffee",
            "direct-dependency": true,
            "version": "2.0.0",
            "latest": "2.0.1",
            "latest-status": "semver-safe-update"
        },
        {
            "name": "drupal/core-composer-scaffold",
            "direct-dependency": true,
            "version": "11.1.4",
            "latest": "11.1.5",
            "latest-status": "semver-safe-update"
        },
        {
            "name": "drupal/core-project-message",
            "direct-dependency": true,
            "version": "11.1.4",
            "latest": "11.1.5",
            "latest-status": "semver-safe-update"
        },
        {
            "name": "drupal/core-recommended",
            "direct-dependency": true,
            "version": "11.1.4",
            "latest": "11.1.5",
            "latest-status": "semver-safe-update"
        },
        {
            "name": "drupal/gin",
            "direct-dependency": true,
            "version": "4.0.5",
            "latest": "4.0.6",
            "latest-status": "semver-safe-update"
        },
        {
            "name": "drupal/pathauto",
            "direct-dependency": true,
            "version": "1.13.0",
            "latest": "1.14.0",
            "latest-status": "semver-safe-update"
        }
    ]
}
';

    public function testGroupUpdatesBeyondConstraint() : void
    {
        $this->cosy->run();
        // The contrib group is allowed (through its rule config) to go beyond
        // the constraint, so it should be updated with a composer require that
        // bumps both packages to their latest version.
        self::assertTrue($this->foundContribRequire, 'The contrib group was required beyond its constraint');
        // Only the packages in the group that actually have an update available
        // are part of the require command. The two that need to go beyond get a
        // new constraint, while drupal/pathauto (which can reach its latest
        // version inside its constraint) keeps the constraint it already has.
        $required_packages = array_filter($this->contribRequireCommand, function ($argument) {
            return strpos($argument, ':') !== false;
        });
        self::assertSame([
            'drupal/coffee:2.0.1',
            'drupal/gin:4.0.6',
            'drupal/pathauto:>=1.13',
        ], array_values($required_packages));
        // None of the packages are dev requirements, so this should not be
        // required with --dev.
        self::assertNotContains('--dev', $this->contribRequireCommand);
        // The core group is not allowed to go beyond, and stays inside its
        // constraint, so it should still use a regular composer update.
        self::assertTrue($this->foundCoreUpdate, 'The core group was updated within its constraint');
        // And a pull request should have been created for the contrib group.
        $heads = array_column($this->prParamsArray, 'head');
        self::assertContains('minor-and-patch-contrib', $heads);
    }

    protected function placeInitialComposerLock() : void
    {
        // The lock contents are identical to the base group fixture, so reuse
        // them instead of duplicating the (large) lock fixtures.
        $this->placeComposerLockContentsFromFixture('composer-group-contrib-and-core.lock', $this->dir);
    }

    /**
     * @param string[] $cmd
     * @param mixed $return
     */
    protected function handleExecutorReturnCallback(array $cmd, &$return) : void
    {
        // The contrib group goes beyond its constraint, so it is updated with a
        // "composer require" of both packages at their latest version.
        $require_parts = ['composer', 'require', 'drupal/coffee:2.0.1', 'drupal/gin:4.0.6'];
        if (count(array_intersect($require_parts, $cmd)) === count($require_parts)) {
            $this->foundContribRequire = true;
            $this->contribRequireCommand = $cmd;
            $this->placeComposerLockContentsFromFixture('composer-group-contrib-and-core.lock.updated_contrib', $this->dir);
        }
        // The core group stays within its constraint (and does not enable
        // beyond), so it is still updated with a regular composer update.
        $core_update_parts = ['composer', 'update', 'drupal/core-project-message', 'drupal/core-recommended'];
        if (count(array_intersect($core_update_parts, $cmd)) === count($core_update_parts)) {
            $this->foundCoreUpdate = true;
            $this->placeComposerLockContentsFromFixture('composer-group-contrib-and-core.lock.updated_core', $this->dir);
        }
    }
}
