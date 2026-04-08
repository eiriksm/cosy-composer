<?php

namespace eiriksm\CosyComposerTest\integration;

class SecurityUpdatesOnlyRuleExceptionTest extends ComposerUpdateIntegrationBase
{

    /** @var string */
    protected $composerAssetFiles = 'composer.security_updates_only_rule_exception';
    /** @var string */
    protected $packageForUpdateOutput = 'psr/log';
    /** @var string */
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    /** @var string */
    protected $packageVersionForToUpdateOutput = '1.1.4';

    public function testPackageWithRuleExceptionIsUpdatedWithoutSecurityAdvisory(): void
    {
        // No security alerts mocked — psr/log has no advisory.
        // But the rule overrides security_updates_only to 0 for psr/log,
        // so it should still be updated.
        $this->runtestExpectedOutput();
        self::assertNotEmpty($this->prParams, 'Expected to have PR params for psr/log but no PR params found');
    }
}
