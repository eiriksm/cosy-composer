<?php

namespace eiriksm\CosyComposerTest\integration;

use Composer\Console\Application;
use eiriksm\ArrayOutput\ArrayOutput;
use eiriksm\CosyComposer\CommandExecuter;
use Symfony\Component\Console\Input\InputDefinition;

class NoUpdatesTest extends Base
{
    public function testNoUpdates()
    {
        $c = $this->getMockCosy();
        $dir = '/tmp/' . uniqid();
        mkdir($dir);
        $c->setTmpDir($dir);
        // Create a mock app, that can respond to things.
        $mock_definition = $this->createMock(InputDefinition::class);
        $mock_definition->method('getOptions')
            ->willReturn([]);
        $mock_app = $this->createMock(Application::class);
        $mock_app->method('getDefinition')
            ->willReturn($mock_definition);
        $c->setApp($mock_app);
        $mock_output = $this->createMock(ArrayOutput::class);
        $mock_output->method('fetch')
            ->willReturn([
                [
                    '{"installed": []}'
                ]
            ]);
        $c->setOutput($mock_output);
        $composer_contents = '{"require": {"drupal/core": "8.0.0"}}';
        $composer_file = "$dir/composer.json";
        file_put_contents($composer_file, $composer_contents);
        $called = false;
        $mock_executer = $this->createMock(CommandExecuter::class);
        $mock_executer->method('executeCommand')
            ->will($this->returnCallback(
                function ($cmd) use (&$called) {
                    if (strpos($cmd, 'rm -rf /tmp/') === 0) {
                        $called = true;
                    }
                    return 0;
                }
            ));
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
        // Create a mock app, that can respond to things.
        $mock_definition = $this->createMock(InputDefinition::class);
        $mock_definition->method('getOptions')
            ->willReturn([]);
        $mock_app = $this->createMock(Application::class);
        $mock_app->method('getDefinition')
            ->willReturn($mock_definition);
        $c->setApp($mock_app);
        $mock_output = $this->createMock(ArrayOutput::class);
        $mock_output->method('fetch')
            ->willReturn([
                [],
                'string',
                [
                    'not_json:TRUE'
                ],
                [
                    '{"not_installed_key": []'
                ]
            ]);
        $c->setOutput($mock_output);
        $composer_contents = '{"require": {"drupal/core": "8.0.0"}}';
        $composer_file = "$dir/composer.json";
        file_put_contents($composer_file, $composer_contents);
        $called = false;
        $mock_executer = $this->createMock(CommandExecuter::class);
        $mock_executer->method('executeCommand')
            ->will($this->returnCallback(
                function ($cmd) use (&$called) {
                    if (strpos($cmd, 'rm -rf /tmp/') === 0) {
                        $called = true;
                    }
                    return 0;
                }
            ));
        $c->setExecuter($mock_executer);
        $this->assertEquals(false, $called);
        $c->run();
        $this->assertEquals(true, $called);
    }

    /**
     * Test that block list works.
     *
     * @dataProvider getBlockListOptions
     */
    public function testNoUpdatesBecauseBlocklisted($opt)
    {
        $c = $this->getMockCosy();
        $dir = '/tmp/' . uniqid();
        mkdir($dir);
        $c->setTmpDir($dir);
        // Create a mock app, that can respond to things.
        $mock_definition = $this->createMock(InputDefinition::class);
        $mock_definition->method('getOptions')
            ->willReturn([]);
        $mock_app = $this->createMock(Application::class);
        $mock_app->method('getDefinition')
            ->willReturn($mock_definition);
        $c->setApp($mock_app);
        $mock_output = $this->createMock(ArrayOutput::class);
        $mock_output->method('fetch')
            ->willReturn([
                [
                    '{"installed": [{"name": "eiriksm/fake-package", "version": "1.0.0", "latest": "1.0.1", "latest-status": "semver-safe-update"}]}'
                ]
            ]);
        $c->setOutput($mock_output);

        $composer_contents = '{"require": {"drupal/core": "8.0.0"}, "extra": {"violinist": { "' . $opt . '": ["eiriksm/fake-package"]}}}';
        $composer_file = "$dir/composer.json";
        file_put_contents($composer_file, $composer_contents);
        $called = false;
        $mock_executer = $this->createMock(CommandExecuter::class);
        $mock_executer->method('executeCommand')
            ->will($this->returnCallback(
                function ($cmd) use (&$called) {
                    if (strpos($cmd, 'rm -rf /tmp/') === 0) {
                        $called = true;
                    }
                    return 0;
                }
            ));
        $c->setExecuter($mock_executer);
        $this->assertEquals(false, $called);
        $c->run();
        $this->assertEquals(true, $called);
        $this->assertOutputContainsMessage('Skipping update of eiriksm/fake-package because it is on the block list', $c);
        $this->assertOutputContainsMessage('No updates found', $c);
    }

    public function getBlockListOptions()
    {
        return [
            [
                'block_list',
            ],
            [
                'blacklist',
            ],
        ];
    }
}
