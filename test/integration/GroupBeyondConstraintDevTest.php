<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Tests a group that goes beyond the constraint, where all packages are dev.
 *
 * Since a "composer require" places the packages in "require" unless it is told
 * otherwise, a group consisting of dev requirements has to be required with the
 * --dev flag. If not, we would move the dev requirements into the production
 * requirements as a side effect of updating them.
 */
class GroupBeyondConstraintDevTest extends ComposerUpdateIntegrationBase
{
    protected ?string $composerAssetFiles = 'composer-group-dev-beyond';

    /**
     * @var string[]
     */
    private array $requireCommand = [];

    /**
     * @var string
     */
    protected $updateJson = '{
    "installed": [
        {
            "name": "filp/whoops",
            "direct-dependency": true,
            "version": "2.14.5",
            "latest": "3.0.0",
            "latest-status": "update-possible"
        },
        {
            "name": "psr/log",
            "direct-dependency": true,
            "version": "1.0.1",
            "latest": "1.1.0",
            "latest-status": "semver-safe-update"
        }
    ]
}
';

    public function testDevGroupIsRequiredAsDev() : void
    {
        $this->cosy->run();
        self::assertNotEmpty($this->requireCommand, 'The group was updated with a composer require');
        // Both packages are dev requirements, so the require has to be done with
        // --dev, or they would be moved into the production requirements.
        self::assertContains('--dev', $this->requireCommand);
        // filp/whoops has to go beyond its "^2.14" constraint to reach 3.0.0,
        // while psr/log reaches 1.1.0 inside its constraint, and therefore keeps
        // the constraint it already has.
        $required_packages = array_filter($this->requireCommand, function ($argument) {
            return strpos($argument, ':') !== false;
        });
        self::assertSame([
            'filp/whoops:^3.0.0',
            'psr/log:^1.0',
        ], array_values($required_packages));
        // And the group update should end up as a pull request.
        $heads = array_column($this->prParamsArray, 'head');
        self::assertContains('dev-tools', $heads);
    }

    /**
     * @param string[] $cmd
     * @param mixed $return
     */
    protected function handleExecutorReturnCallback(array $cmd, &$return) : void
    {
        if (in_array('require', $cmd, true) && in_array('filp/whoops:^3.0.0', $cmd, true)) {
            $this->requireCommand = $cmd;
            $this->placeUpdatedComposerLock();
        }
    }
}
