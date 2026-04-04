<?php

namespace eiriksm\CosyComposerTest\integration;

class SecurityUpdatesOnlyRuleExceptionTest extends ComposerUpdateIntegrationBase
{

    protected $composerAssetFiles = 'composer.security_updates_only_rule_exception';
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.1.4';

    public function testPackageWithRuleExceptionIsUpdatedWithoutSecurityAdvisory()
    {
        // No security alerts mocked — psr/log has no advisory.
        // But the rule overrides security_updates_only to 0 for psr/log,
        // so it should still be updated.
        $this->runtestExpectedOutput();
        self::assertNotEmpty($this->prParams);
    }
}
