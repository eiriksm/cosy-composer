<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Tests a group beyond the constraint, spread over require and require-dev.
 *
 * A single "composer require" would place all of the packages in the same
 * section of composer.json, which would move psr/log out of require-dev. Since
 * we can not require the packages of both sections in one command, we rather
 * update the group inside the constraint, like we would have done if the group
 * was not allowed to go beyond the constraint at all.
 */
class GroupBeyondConstraintMixedDevTest extends ComposerUpdateIntegrationBase
{
    protected ?string $composerAssetFiles = 'composer-group-mixed-dev-beyond';

    private bool $foundUpdate = false;

    private bool $foundRequire = false;

    /**
     * @var string
     */
    protected $updateJson = '{
    "installed": [
        {
            "name": "psr/cache",
            "direct-dependency": true,
            "version": "1.0.0",
            "latest": "2.0.0",
            "latest-status": "update-possible"
        },
        {
            "name": "psr/log",
            "direct-dependency": true,
            "version": "1.1.3",
            "latest": "1.1.4",
            "latest-status": "semver-safe-update"
        }
    ]
}
';

    public function testMixedDevGroupIsNotRequired() : void
    {
        $this->cosy->run();
        // psr/cache would have to go beyond its "~1.0.0" constraint to reach
        // 2.0.0, but since the group also contains a dev requirement, we should
        // fall back to updating inside the constraint.
        self::assertFalse($this->foundRequire, 'The mixed group was not updated with a composer require');
        self::assertTrue($this->foundUpdate, 'The mixed group was updated with a composer update');
        $this->assertOutputContainsMessage('spread over both require and require-dev', $this->cosy);
        // The update inside the constraint still bumps psr/log, so there should
        // be a pull request for the group.
        $heads = array_column($this->prParamsArray, 'head');
        self::assertContains('all-the-psr', $heads);
    }

    /**
     * @param string[] $cmd
     * @param mixed $return
     */
    protected function handleExecutorReturnCallback(array $cmd, &$return) : void
    {
        if (!in_array('composer', $cmd, true)) {
            return;
        }
        if (in_array('require', $cmd, true)) {
            $this->foundRequire = true;
        }
        $update_parts = ['update', 'psr/cache', 'psr/log'];
        if (count(array_intersect($update_parts, $cmd)) === count($update_parts)) {
            $this->foundUpdate = true;
            $this->placeUpdatedComposerLock();
        }
    }
}
