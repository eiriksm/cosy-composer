<?php

namespace eiriksm\CosyComposerTest\integration;

class IndividualRuleUpdateWithDependenciesTest extends ComposerUpdateIntegrationBase
{
    protected $composerAssetFiles = 'composer.individual_rule_overrides';
    protected $updateJson = '{
    "installed": [
        {
            "name": "psr/log",
            "direct-dependency": true,
            "version": "1.0.0",
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

    private $packagesUpdatedWithDependencies = [];

    public function testRuleOverridesGlobalUpdateWithDependenciesForIndividualPackages()
    {
        $this->runtestExpectedOutput();
        self::assertContains('psr/log', $this->packagesUpdatedWithDependencies, 'psr/log rule should override global update_with_dependencies=0 to 1');
        self::assertContains('psr/cache', $this->packagesUpdatedWithDependencies, 'psr/cache rule should override global update_with_dependencies=0 to 1');
    }

    protected function handleExecutorReturnCallback(array $cmd, &$return)
    {
        if (!in_array('--with-dependencies', $cmd, true)) {
            return;
        }
        if (in_array('psr/log', $cmd, true)) {
            $this->packagesUpdatedWithDependencies[] = 'psr/log';
            $this->placeComposerLockContentsFromFixture('composer.individual_rule_overrides.lock.psr_log_updated', $this->dir);
        }
        if (in_array('psr/cache', $cmd, true)) {
            $this->packagesUpdatedWithDependencies[] = 'psr/cache';
            $this->placeComposerLockContentsFromFixture('composer.individual_rule_overrides.lock.psr_cache_updated', $this->dir);
        }
    }
}
