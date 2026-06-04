<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Simulates a project that is on security_updates_only, but with a rule that
 * exempts a single package (security_updates_only=0) and updates it with its
 * dependencies (update_with_dependencies=1).
 *
 * The rule with matchRules turns the package into a single group update item,
 * and updating it (with dependencies) ends up pulling in a brand new package
 * that was not installed before.
 */
class SecurityGroupInstallsNewPackageTest extends ComposerUpdateIntegrationBase
{
    /** @var string */
    protected $composerAssetFiles = 'composer.security_group_installs_new_package';

    /** @var string */
    protected $updateJson = '{
    "installed": [
        {
            "name": "dummyvendor/base-module",
            "direct-dependency": true,
            "version": "1.0.0",
            "latest": "2.0.0",
            "latest-status": "update-possible",
            "abandoned": false
        }
    ]
}';

    public function testGroupUpdateInstallsNewPackage(): void
    {
        // No security alerts are mocked, so under the global
        // security_updates_only=1 the package would normally be skipped. The
        // rule overrides it to 0, so the (single) group update still happens.
        $this->runtestExpectedOutput();

        // Exactly one group update item is available, hence exactly one PR.
        self::assertCount(1, $this->prParamsArray, 'Expected exactly one group update PR to be created');
        self::assertEquals(
            'Update group `Dummy Vendor Base Module`',
            $this->prParams['title'],
            'The single PR should be the group update for the matched rule'
        );

        // The matched package was updated from 1.0.0 to 2.0.0.
        self::assertStringContainsString('| dummyvendor/base-module | `1.0.0` | `2.0.0` |', $this->prParams['body']);
        // Updating it (with dependencies) installed a brand new package that
        // was not present in the lock file before, hence the empty "current
        // version" column for it.
        self::assertStringContainsString('| dummyvendor/base-library | `` | `1.0.0` |', $this->prParams['body']);
        self::assertStringContainsString('## dummyvendor/base-library ( → 1.0.0)', $this->prParams['body']);
    }

    /**
     * @param array<string> $cmd
     * @param int $return
     */
    public function handleExecutorReturnCallback(array $cmd, &$return): void
    {
        // When the group update for the module is executed, swap in the lock
        // file that reflects the bumped module plus the newly installed library.
        if (in_array('composer', $cmd, true)
            && in_array('update', $cmd, true)
            && in_array('dummyvendor/base-module', $cmd, true)) {
            $this->placeComposerLockContentsFromFixture('composer.security_group_installs_new_package.lock.updated', $this->dir);
        }
    }
}
