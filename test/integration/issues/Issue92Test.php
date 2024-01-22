<?php

namespace eiriksm\CosyComposerTest\integration\issues;

use eiriksm\CosyComposer\CommandExecuter;
use eiriksm\CosyComposerTest\integration\Base;
use eiriksm\CosyComposerTest\integration\ComposerUpdateIntegrationBase;

/**
 * Class Issue92Test.
 *
 * Issue 92 was that after we switched the updater package, the output from the failed composer update command would not
 * get logged.
 */
class Issue92Test extends ComposerUpdateIntegrationBase
{
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.0.2';
    protected $composerAssetFiles = 'composer-psr-log';

    public function testIssue92()
    {
        $this->runtestExpectedOutput();
        $called = false;
        $mock_executer = $this->createMock(CommandExecuter::class);
        $current_error_output = '';
        $mock_executer->method('executeCommand')
            ->will($this->returnCallback(
                function ($cmd) use (&$called, &$current_error_output) {
                    $current_error_output = '';
                    if ($cmd == $this->createExpectedCommandForPackage('psr/log')) {
                        $current_error_output = "Trying to update\nFailed to update";
                    }
                    $return = 0;
                    $cmd_string = implode(' ', $cmd);
                    if (strpos($cmd_string, 'rm -rf /tmp/') === 0) {
                        $called = true;
                    }
                    $this->lastCommand = $cmd;
                    return $return;
                }
            ));
        $mock_executer->method('getLastOutput')
            ->willReturnCallback(function () use (&$current_error_output) {
                $last_command_string = implode(' ', $this->lastCommand);
                if (mb_strpos($last_command_string, 'composer outdated') === 0) {
                    return [
                        'stdout' => $this->updateJson,
                    ];
                }
                return [
                   'stdout' => '',
                   'stderr' =>  $current_error_output,
                ];
            });
        $c->setExecuter($mock_executer);
        $this->assertEquals(false, $called);

        // Then we are going to mock the provider factory.
        $this->registerProviderFactory($c);
        $this->assertEquals(false, $called);
        $this->placeComposerLockContentsFromFixture('composer-psr-log.lock', $dir);
        $c->run();
        $this->assertOutputContainsMessage('Trying to update
Failed to update', $c);
        $this->assertOutputContainsMessage('psr/log was not updated running composer update', $c);
        $this->assertEquals(true, $called);
    }
}
