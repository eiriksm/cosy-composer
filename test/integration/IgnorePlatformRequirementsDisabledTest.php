<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\CommandExecuter;

/**
 * Test that ignore_platform_requirements is NOT enabled when set to 0 or not present.
 */
class IgnorePlatformRequirementsDisabledTest extends ComposerUpdateIntegrationBase
{
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.1.4';
    protected $composerAssetFiles = 'composer-no-ignore-platform';
    protected $checkPrUrl = true;

    /**
     * @var bool
     */
    protected $ignorePlatformRequirementsCalled = false;

    /**
     * @var bool
     */
    protected $ignorePlatformRequirementsValue = true; // Start with true to detect if it's set to false

    public function setUp(): void
    {
        parent::setUp();

        // Create a partial mock that allows us to track setIgnorePlatformRequirements calls
        $mock_executer = $this->createPartialMock(CommandExecuter::class, ['executeCommand', 'getLastOutput', 'setIgnorePlatformRequirements']);

        $mock_executer->method('executeCommand')
            ->willReturnCallback(function ($cmd) {
                $return = 0;
                $expected_command = $this->createExpectedCommandForPackage($this->packageForUpdateOutput);
                if ($cmd == $expected_command) {
                    $this->placeUpdatedComposerLock();
                }
                $this->handleExecutorReturnCallback($cmd, $return);
                $this->lastCommand = $cmd;
                return $return;
            });

        $mock_executer->method('setIgnorePlatformRequirements')
            ->willReturnCallback(function ($value) {
                $this->ignorePlatformRequirementsCalled = true;
                $this->ignorePlatformRequirementsValue = $value;
            });

        $this->ensureMockExecuterProvidesLastOutput($mock_executer);
        $this->cosy->setExecuter($mock_executer);
    }

    public function testIgnorePlatformRequirementsDisabled()
    {
        $this->runtestExpectedOutput();

        // Assert that setIgnorePlatformRequirements was called with false
        self::assertTrue($this->ignorePlatformRequirementsCalled, 'setIgnorePlatformRequirements should have been called');
        self::assertFalse($this->ignorePlatformRequirementsValue, 'setIgnorePlatformRequirements should have been called with false');

        // Assert that the log message does NOT appear
        $found = $this->findMessage('Ignoring platform requirements for composer commands', $this->cosy);
        self::assertFalse($found, 'Log message about ignoring platform requirements should not appear when disabled');
    }
}
