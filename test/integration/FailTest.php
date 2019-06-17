<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\ArrayOutput\ArrayOutput;
use eiriksm\CosyComposer\CommandExecuter;
use eiriksm\CosyComposer\Exceptions\ChdirException;

class FailTest extends Base
{

    public function testChdirFail()
    {
        $c = $this->getMockCosy();
        $c->setTmpParent('/stupid/nonexistent');
        $this->expectException(ChdirException::class);
        $this->expectExceptionMessage('Problem with changing dir to /stupid/nonexistent');
        $c->run();
    }

    public function testGitFail()
    {
        $c = $this->getMockCosy();
        $tmp_dir = '/tmp/' . uniqid();
        $c->setTmpDir($tmp_dir);
        $mock_executer = $this->createMock(CommandExecuter::class);
        $mock_executer->method('executeCommand')
            ->will($this->returnCallback(
                function ($cmd, $log = true, $timeout = 120) {

                    if (strpos($cmd, 'git clone --depth=1 https://user-token:@github.com/a/b') === 0) {
                        return 42;
                    }
                    return 0;
                }
            ));
        $c->setExecuter($mock_executer);
        $this->expectExceptionMessage('Problem with the execCommand git clone. Exit code was 42');
        $c->run();
    }

    public function testChdirToCloneFail()
    {
        $c = $this->getMockCosy();
        $mock_executer = $this->createMock(CommandExecuter::class);
        $mock_executer->method('executeCommand')
            ->will($this->returnCallback(
                function ($cmd) {
                    return 0;
                }
            ));
        $this->expectExceptionMessage('Problem with changing dir to the clone dir.');
        $this->expectException(ChdirException::class);
        $c->setExecuter($mock_executer);
        $c->run();
    }

    public function testNoComposerFile()
    {
        $c = $this->getMockCosy();
        $dir = '/tmp/' . uniqid();
        mkdir($dir);
        $c->setTmpDir($dir);
        $mock_executer = $this->createMock(CommandExecuter::class);
        $mock_executer->method('executeCommand')
            ->will($this->returnCallback(
                function ($cmd) {
                    return 0;
                }
            ));
        $this->expectExceptionMessage('No composer.json file found.');
        $this->expectException(\InvalidArgumentException::class);
        $c->setExecuter($mock_executer);
        $c->run();
    }

    public function testInvalidComposerFile()
    {
        $c = $this->getMockCosy();
        $dir = '/tmp/' . uniqid();
        mkdir($dir);
        $c->setTmpDir($dir);
        file_put_contents("$dir/composer.json", '{not:json]');
        $mock_executer = $this->createMock(CommandExecuter::class);
        $mock_executer->method('executeCommand')
            ->will($this->returnCallback(
                function ($cmd) {
                    return 0;
                }
            ));
        $this->expectExceptionMessage('Invalid composer.json file');
        $this->expectException(\InvalidArgumentException::class);
        $c->setExecuter($mock_executer);
        $c->run();
    }

    public function testInvalidUpdateData()
    {
        $c = $this->getMockCosy();
        $dir = '/tmp/' . uniqid();
        mkdir($dir);
        $c->setTmpDir($dir);
        // Create a mock app, that can respond to things.
        $mock_definition = $this->createMock('Symfony\Component\Console\Input\InputDefinition');
        $mock_definition->method('getOptions')
            ->willReturn([]);
        $mock_app = $this->createMock('Composer\Console\Application');
        $mock_app->method('getDefinition')
            ->willReturn($mock_definition);
        $c->setApp($mock_app);
        $mock_output = $this->createMock(ArrayOutput::class);
        $mock_output->method('fetch')
            ->willReturn([
                [
                    '{"json": 1}'
                ]
            ]);
        $c->setOutput($mock_output);
        file_put_contents("$dir/composer.json", '{"require": {"drupal/core": "8.0.0"}}');
        $mock_executer = $this->createMock(CommandExecuter::class);
        $mock_executer->method('executeCommand')
            ->will($this->returnCallback(
                function ($cmd) {
                    return 0;
                }
            ));
        $this->expectExceptionMessage('JSON output from composer was not looking as expected after checking updates');
        $this->expectException(\Exception::class);
        $c->setExecuter($mock_executer);
        $c->run();
    }
}
