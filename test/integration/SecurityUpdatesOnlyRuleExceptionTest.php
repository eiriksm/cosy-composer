<?php

namespace eiriksm\CosyComposerTest\integration;

class SecurityUpdatesOnlyRuleExceptionTest extends UpdateAllBase
{

    protected $composerJson = 'composer.security_updates_only_rule_exception.json';

    public function testPackageWithRuleExceptionIsUpdatedWithoutSecurityAdvisory()
    {
        // No security alerts mocked — psr/log has no advisory.
        // But the rule overrides security_updates_only to 0 for psr/log,
        // so it should still be updated.
        $this->cosy->run();
        self::assertTrue($this->foundCommand);
        self::assertTrue($this->foundBranch);
        self::assertTrue($this->foundCommit);
    }
}
