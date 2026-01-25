<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\CommandExecuter;
use Violinist\Slug\Slug;

/**
 * Test for ignore_platform_requirements configuration option.
 */
class IgnorePlatformRequirementsTest extends ComposerUpdateIntegrationBase
{
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.1.4';
    protected $composerAssetFiles = 'composer-ignore-platform';
    protected $checkPrUrl = true;

    /**
     * @var bool
     */
    protected $ignorePlatformRequirementsCalled = false;

    /**
     * @var bool
     */
    protected $ignorePlatformRequirementsValue = false;

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

    public function testIgnorePlatformRequirementsEnabled()
    {
        $this->runtestExpectedOutput();

        // Assert that setIgnorePlatformRequirements was called with true
        self::assertTrue($this->ignorePlatformRequirementsCalled, 'setIgnorePlatformRequirements should have been called');
        self::assertTrue($this->ignorePlatformRequirementsValue, 'setIgnorePlatformRequirements should have been called with true');

        // Assert that the log message appears
        $this->assertOutputContainsMessage('Ignoring platform requirements for composer commands', $this->cosy);
    }
}
