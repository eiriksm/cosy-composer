<?php

namespace eiriksm\CosyComposerTest\integration;

use Composer\Console\Application;
use eiriksm\ArrayOutput\ArrayOutput;
use eiriksm\CosyComposer\CommandExecuter;
use eiriksm\CosyComposer\Message;
use eiriksm\CosyComposer\ProviderFactory;
use eiriksm\CosyComposer\Providers\Github;
use eiriksm\CosyComposer\Providers\PublicGithubWrapper;
use Github\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputDefinition;
use Violinist\ProjectData\ProjectData;
use Violinist\Slug\Slug;

class UpdatesTest extends Base
{
    public function testUpdatesFoundButProviderDoesNotAuthenticate()
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
        $mock_output = $this->getMockOutputWithUpdate('eiriksm/fake-package', '1.0.0', '1.0.1');
        $c->setOutput($mock_output);
        $composer_contents = '{"require": {"eiriksm/fake-package": "1.0.0"}}';
        $composer_file = "$dir/composer.json";
        file_put_contents($composer_file, $composer_contents);
        $called = false;
        $mock_executer = $this->createMock(CommandExecuter::class);
        $mock_executer->method('executeCommand')
            ->willReturn(0);
        $c->setExecuter($mock_executer);
        $this->assertEquals(false, $called);

        // Then we are going to mock the provider factory.
        $mock_provider_factory = $this->createMock(ProviderFactory::class);
        $mock_provider = $this->createMock(Github::class);
        $mock_provider->method('authenticate')
            ->willThrowException(new RuntimeException('Bad credentials'));
        $mock_provider_factory->method('createFromHost')
            ->willReturn($mock_provider);

        $c->setProviderFactory($mock_provider_factory);
        $this->expectException(RuntimeException::class);
        $c->run();
    }

    public function testUpdatesFoundButAllPushed()
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
                    $this->createUpdateJsonFromData('eiriksm/fake-package', '1.0.0', '1.0.1'),
                ]
            ]);
        $c->setOutput($mock_output);
        $composer_contents = '{"require": {"drupal/core": "8.0.0", "eiriksm/fake-package": "^1.0"}}';
        $composer_file = "$dir/composer.json";
        file_put_contents($composer_file, $composer_contents);
        $called = false;
        $mock_executer = $this->getMockExecuterWithReturnCallback(
            function ($cmd) use (&$called) {
                $cmd_string = implode(' ', $cmd);
                if (strpos($cmd_string, 'rm -rf /tmp/') === 0) {
                    $called = true;
                }
                return 0;
            }
        );
        $c->setExecuter($mock_executer);
        $this->assertEquals(false, $called);

        // Then we are going to mock the provider factory.
        $mock_provider_factory = $this->createMock(ProviderFactory::class);
        $mock_provider = $this->createMock(Github::class);
        $mock_provider->method('repoIsPrivate')
            ->willReturn(true);
        $mock_provider->method('getDefaultBranch')
            ->willReturn('master');
        $mock_provider->method('getBranchesFlattened')
            ->willReturn([
                'eiriksmfakepackage100101',
            ]);
        $default_sha = 123;
        $mock_provider->method('getDefaultBase')
            ->willReturn($default_sha);
        $mock_provider->method('getPrsNamed')
            ->willReturn([
                'eiriksmfakepackage100101' => [
                    'base' => [
                        'sha' => $default_sha,
                    ],
                    'number' => 123,
                    'title' => 'Update eiriksm/fake-package from 1.0.0 to 1.0.1',
                ],
            ]);
        $mock_provider_factory->method('createFromHost')
            ->willReturn($mock_provider);

        $c->setProviderFactory($mock_provider_factory);
        $this->assertEquals(false, $called);
        $c->run();
        $message = $this->findMessage('Skipping eiriksm/fake-package because a pull request already exists', $c);
        $this->assertEquals(Message::PR_EXISTS, $message->getType());
        $this->assertTrue(!empty($message));
        $this->assertEquals('eiriksm/fake-package', $message->getContext()["package"]);
        $this->assertEquals(true, $called);
    }

    public function testUpdatesFoundButInvalidPackage()
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
                    $this->createUpdateJsonFromData('eiriksm/fake-package', '1.0.0', '1.0.1'),
                ]
            ]);
        $c->setOutput($mock_output);
        $composer_contents = file_get_contents(__DIR__ . '/../fixtures/composer-psr-log.json');
        $composer_file = "$dir/composer.json";
        file_put_contents($composer_file, $composer_contents);
        $called = false;
        $mock_executer = $mock_executer = $this->getMockExecuterWithReturnCallback(
            function ($cmd) use (&$called) {
                $cmd_string = implode(' ', $cmd);
                if (strpos($cmd_string, 'rm -rf /tmp/') === 0) {
                    $called = true;
                }
                return 0;
            }
        );
        $c->setExecuter($mock_executer);
        $this->assertEquals(false, $called);

        // Then we are going to mock the provider factory.
        $mock_provider_factory = $this->createMock(ProviderFactory::class);
        $mock_provider = $this->createMock(Github::class);
        $mock_provider->method('repoIsPrivate')
            ->willReturn(true);
        $mock_provider->method('getDefaultBranch')
            ->willReturn('master');
        $mock_provider->method('getBranchesFlattened')
            ->willReturn([]);
        $default_sha = 123;
        $mock_provider->method('getDefaultBase')
            ->willReturn($default_sha);
        $mock_provider->method('getPrsNamed')
            ->willReturn([]);
        $mock_provider_factory->method('createFromHost')
            ->willReturn($mock_provider);

        $c->setProviderFactory($mock_provider_factory);
        $this->assertEquals(false, $called);
        $composer_lock_contents = file_get_contents(__DIR__ . '/../fixtures/composer-psr-log.lock');
        file_put_contents("$dir/composer.lock", $composer_lock_contents);
        $c->run();
        $this->assertOutputContainsMessage('Caught an exception: Did not find the requested package (eiriksm/fake-package) in the lockfile. This is probably an error', $c);
        $this->assertEquals(true, $called);
    }

    public function testUpdatesFoundButNotSemverValid()
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
                    $this->createUpdateJsonFromData('psr/log', '1.0.0', '2.0.1'),
                ]
            ]);
        $c->setOutput($mock_output);
        $composer_contents = file_get_contents(__DIR__ . '/../fixtures/composer-psr-log-with-extra-allow-beyond.json');
        $composer_file = "$dir/composer.json";
        file_put_contents($composer_file, $composer_contents);
        $called = false;
        $mock_executer = $this->createMock(CommandExecuter::class);
        $mock_executer->method('executeCommand')
            ->will($this->returnCallback(
                function ($cmd) use (&$called) {
                    $cmd_string = implode(' ', $cmd);
                    if (strpos($cmd_string, 'rm -rf /tmp/') === 0) {
                        $called = true;
                    }
                    return 0;
                }
            ));
        $c->setExecuter($mock_executer);
        $this->assertEquals(false, $called);

        // Then we are going to mock the provider factory.
        $mock_provider_factory = $this->createMock(ProviderFactory::class);
        $mock_provider = $this->createMock(Github::class);
        $mock_provider->method('repoIsPrivate')
            ->willReturn(true);
        $mock_provider->method('getDefaultBranch')
            ->willReturn('master');
        $mock_provider->method('getBranchesFlattened')
            ->willReturn([]);
        $default_sha = 123;
        $mock_provider->method('getDefaultBase')
            ->willReturn($default_sha);
        $mock_provider->method('getPrsNamed')
            ->willReturn([]);
        $mock_provider_factory->method('createFromHost')
            ->willReturn($mock_provider);

        $c->setProviderFactory($mock_provider_factory);
        $this->assertEquals(false, $called);
        $composer_lock_contents = file_get_contents(__DIR__ . '/../fixtures/composer-psr-log.lock');
        file_put_contents("$dir/composer.lock", $composer_lock_contents);
        $c->run();
        $this->assertOutputContainsMessage('Package psr/log with the constraint ^1.0 can not be updated to 2.0.1.', $c);
        $this->assertEquals(true, $called);
    }

    public function testUpdatesFoundButComposerUpdateFails()
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
                    $this->createUpdateJsonFromData('psr/log', '1.0.0', '1.0.2'),
                ]
            ]);
        $c->setOutput($mock_output);
        $composer_contents = file_get_contents(__DIR__ . '/../fixtures/composer-psr-log.json');
        $composer_file = "$dir/composer.json";
        file_put_contents($composer_file, $composer_contents);
        $called = false;
        $mock_executer = $this->createMock(CommandExecuter::class);
        $composer_update_called = false;
        $mock_executer->method('executeCommand')
            ->will($this->returnCallback(
                function ($cmd) use (&$called, &$composer_update_called) {
                    $return = 0;
                    if ($cmd == $this->createExpectedCommandForPackage('psr/log')) {
                        $composer_update_called = true;
                        $return = 1;
                    }
                    $cmd_string = implode(' ', $cmd);
                    if (strpos($cmd_string, 'rm -rf /tmp/') === 0) {
                        $called = true;
                    }
                    return $return;
                }
            ));
        $c->setExecuter($mock_executer);
        $this->assertEquals(false, $called);

        // Then we are going to mock the provider factory.
        $mock_provider_factory = $this->createMock(ProviderFactory::class);
        $mock_provider = $this->createMock(Github::class);
        $mock_provider->method('repoIsPrivate')
            ->willReturn(true);
        $mock_provider->method('getDefaultBranch')
            ->willReturn('master');
        $mock_provider->method('getBranchesFlattened')
            ->willReturn([]);
        $default_sha = 123;
        $mock_provider->method('getDefaultBase')
            ->willReturn($default_sha);
        $mock_provider->method('getPrsNamed')
            ->willReturn([]);
        $mock_provider_factory->method('createFromHost')
            ->willReturn($mock_provider);

        $c->setProviderFactory($mock_provider_factory);
        $this->assertEquals(false, $called);
        $composer_lock_contents = file_get_contents(__DIR__ . '/../fixtures/composer-psr-log.lock');
        file_put_contents("$dir/composer.lock", $composer_lock_contents);
        $c->run();
        $this->assertOutputContainsMessage('Caught an exception: Composer update exited with exit code 1', $c);
        $this->assertEquals(true, $called);
        $this->assertEquals(true, $composer_update_called);
    }

    public function testNotUpdatedInComposerLock()
    {
        $c = $this->getMockCosy();
        $dir = '/tmp/' . uniqid();
        $this->setupDirectory($c, $dir);
        // Create a mock app, that can respond to things.
        $definition = $this->getMockDefinition();
        $mock_app = $this->getMockApp($definition);
        $c->setApp($mock_app);
        $mock_output = $this->getMockOutputWithUpdate('psr/log', '1.0.0', '1.0.2');
        $c->setOutput($mock_output);
        $this->placeComposerContentsFromFixture('composer-psr-log.json', $dir);
        $called = false;
        $mock_executer = $this->createMock(CommandExecuter::class);
        $mock_executer->method('executeCommand')
            ->will($this->returnCallback(
                function ($cmd) use (&$called) {
                    $return = 0;
                    $cmd_string = implode(' ', $cmd);
                    if (strpos($cmd_string, 'rm -rf /tmp/') === 0) {
                        $called = true;
                    }
                    return $return;
                }
            ));
        $c->setExecuter($mock_executer);
        $this->assertEquals(false, $called);

        // Then we are going to mock the provider factory.
        $mock_provider_factory = $this->createMock(ProviderFactory::class);
        $mock_provider = $this->createMock(Github::class);
        $mock_provider->method('repoIsPrivate')
            ->willReturn(true);
        $mock_provider->method('getDefaultBranch')
            ->willReturn('master');
        $mock_provider->method('getBranchesFlattened')
            ->willReturn([]);
        $default_sha = 123;
        $mock_provider->method('getDefaultBase')
            ->willReturn($default_sha);
        $mock_provider->method('getPrsNamed')
            ->willReturn([]);
        $mock_provider_factory->method('createFromHost')
            ->willReturn($mock_provider);

        $c->setProviderFactory($mock_provider_factory);
        $this->assertEquals(false, $called);
        $composer_lock_contents = file_get_contents(__DIR__ . '/../fixtures/composer-psr-log.lock');
        file_put_contents("$dir/composer.lock", $composer_lock_contents);
        $c->run();
        $this->assertOutputContainsMessage('psr/log was not updated running composer update', $c);
        $this->assertEquals(true, $called);
    }

    public function testUpdatesRunButErrorCommiting()
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
                    $this->createUpdateJsonFromData('psr/log', '1.0.0', '1.0.2'),
                ]
            ]);
        $c->setOutput($mock_output);
        $composer_contents = file_get_contents(__DIR__ . '/../fixtures/composer-psr-log.json');
        $composer_file = "$dir/composer.json";
        file_put_contents($composer_file, $composer_contents);
        $called = false;
        $mock_executer = $this->createMock(CommandExecuter::class);
        $mock_executer->method('executeCommand')
            ->will($this->returnCallback(
                function ($cmd) use (&$called, $dir) {
                    $return = 0;
                    $command = $this->createExpectedCommandForPackage('psr/log');
                    if ($cmd == $command) {
                        file_put_contents("$dir/composer.lock", file_get_contents(__DIR__ . '/../fixtures/composer-psr-log.lock-updated'));
                    }
                    if ($cmd == ['git', 'commit', 'composer.json', 'composer.lock', '-m', 'Update psr/log']) {
                        $return = 1;
                    }
                    $cmd_string = implode(' ', $cmd);
                    if (strpos($cmd_string, 'rm -rf /tmp/') === 0) {
                        $called = true;
                    }
                    return $return;
                }
            ));
        $c->setExecuter($mock_executer);
        $this->assertEquals(false, $called);

        // Then we are going to mock the provider factory.
        $mock_provider_factory = $this->createMock(ProviderFactory::class);
        $mock_provider = $this->createMock(Github::class);
        $mock_provider->method('repoIsPrivate')
            ->willReturn(true);
        $mock_provider->method('getDefaultBranch')
            ->willReturn('master');
        $mock_provider->method('getBranchesFlattened')
            ->willReturn([]);
        $default_sha = 123;
        $mock_provider->method('getDefaultBase')
            ->willReturn($default_sha);
        $mock_provider->method('getPrsNamed')
            ->willReturn([]);
        $mock_provider_factory->method('createFromHost')
            ->willReturn($mock_provider);

        $c->setProviderFactory($mock_provider_factory);
        $this->assertEquals(false, $called);
        $composer_lock_contents = file_get_contents(__DIR__ . '/../fixtures/composer-psr-log.lock');
        file_put_contents("$dir/composer.lock", $composer_lock_contents);
        $c->run();
        $this->assertOutputContainsMessage('Caught an exception: Error committing the composer files. They are probably not changed.', $c);
        $this->assertEquals(true, $called);
    }
}
