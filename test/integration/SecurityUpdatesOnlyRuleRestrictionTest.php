<?php

namespace eiriksm\CosyComposerTest\integration;

class SecurityUpdatesOnlyRuleRestrictionTest extends ComposerUpdateIntegrationBase
{

    /** @var string */
    protected $composerAssetFiles = 'composer.security_updates_only_rule_restriction';
    /** @var string */
    protected $packageForUpdateOutput = 'psr/log';
    /** @var string */
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    /** @var string */
    protected $packageVersionForToUpdateOutput = '1.1.4';

    public function testPackageWithRuleRestrictionIsNotUpdatedWithoutSecurityAdvisory(): void
    {
        // No security alerts mocked — psr/log has no advisory.
        // The global security_updates_only is not set, but the per-package rule
        // sets security_updates_only to 1 for psr/log.
        // It should therefore NOT be updated.
        $this->runtestExpectedOutput();
        self::assertEmpty($this->prParams, 'Expected no PR for psr/log since its rule requires security updates only, but there is no security advisory');
    }
}
