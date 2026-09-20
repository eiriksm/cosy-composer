<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\CommandExecuter;

class NoUpdatesTest extends Base
{
    public function testNoUpdates()
    {
        $c = $this->getMockCosy();
        $dir = '/tmp/' . uniqid();
        mkdir($dir);
        $c->setTmpDir($dir);
        $this->updateJson = '{"installed": []}';
        $composer_contents = '{"require": {"drupal/core": "8.0.0"}}';
        $composer_file = "$dir/composer.json";
        file_put_contents($composer_file, $composer_contents);
        $called = false;
        $mock_executer = $this->createMock(CommandExecuter::class);
        $mock_executer->method('executeCommand')
            ->will($this->returnCallback(
                function ($cmd) use (&$called) {
                    $this->lastCommand = $cmd;
                    $cmd_string = implode(' ', $cmd);
                    if (strpos($cmd_string, 'rm -rf /tmp/') === 0) {
                        $called = true;
                    }
                    return 0;
                }
            ));
        $this->ensureMockExecuterProvidesLastOutput($mock_executer);
        $c->setExecuter($mock_executer);
        $this->assertEquals(false, $called);
        $c->run();
        $this->assertEquals(true, $called);
    }

    public function testNoUpdatesBadDataLines()
    {
        $c = $this->getMockCosy();
        $dir = '/tmp/' . uniqid();
        mkdir($dir);
        $c->setTmpDir($dir);
        $this->updateJson = '{"not_installed_key": []}';
        $composer_contents = '{"require": {"drupal/core": "8.0.0"}}';
        $composer_file = "$dir/composer.json";
        file_put_contents($composer_file, $composer_contents);
        $called = false;
        $mock_executer = $this->createMock(CommandExecuter::class);
        $mock_executer->method('executeCommand')
            ->will($this->returnCallback(
                function ($cmd) use (&$called) {
                    $this->lastCommand = $cmd;
                    $cmd_string = implode(' ', $cmd);
                    if (strpos($cmd_string, 'rm -rf /tmp/') === 0) {
                        $called = true;
                    }
                    return 0;
                }
            ));
        $this->ensureMockExecuterProvidesLastOutput($mock_executer);
        $c->setExecuter($mock_executer);
        $this->assertEquals(false, $called);
        $this->expectExceptionMessage('JSON output from composer was not looking as expected after checking updates');
        $c->run();
        $this->assertEquals(true, $called);
    }

    public function testNoUpdatesWorseDataLines()
    {
        $c = $this->getMockCosy();
        $dir = '/tmp/' . uniqid();
        mkdir($dir);
        $c->setTmpDir($dir);
        $this->updateJson = '{"installed": 1}';
        $composer_contents = '{"require": {"drupal/core": "8.0.0"}}';
        $composer_file = "$dir/composer.json";
        file_put_contents($composer_file, $composer_contents);
        $called = false;
        $mock_executer = $this->createMock(CommandExecuter::class);
        $mock_executer->method('executeCommand')
            ->will($this->returnCallback(
                function ($cmd) use (&$called) {
                    $this->lastCommand = $cmd;
                    $cmd_string = implode(' ', $cmd);
                    if (strpos($cmd_string, 'rm -rf /tmp/') === 0) {
                        $called = true;
                    }
                    return 0;
                }
            ));
        $this->ensureMockExecuterProvidesLastOutput($mock_executer);
        $c->setExecuter($mock_executer);
        $this->assertEquals(false, $called);
        $c->run();
        $this->assertEquals(true, $called);
        $this->assertOutputContainsMessage('Update data was in wrong format or missing. This is an error in violinist and should be reported', $c);
    }
}
