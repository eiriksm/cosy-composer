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
                if (strpos($cmd, 'rm -rf /tmp/') === 0) {
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
                if (strpos($cmd, 'rm -rf /tmp/') === 0) {
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
                    if (strpos($cmd, 'rm -rf /tmp/') === 0) {
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
                    if (strpos($cmd, 'rm -rf /tmp/') === 0) {
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
                    if (strpos($cmd, 'rm -rf /tmp/') === 0) {
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
                    if ($cmd == 'GIT_AUTHOR_NAME="" GIT_AUTHOR_EMAIL="" GIT_COMMITTER_NAME="" GIT_COMMITTER_EMAIL="" git commit composer.json composer.lock -m "Update psr/log"') {
                        $return = 1;
                    }
                    if (strpos($cmd, 'rm -rf /tmp/') === 0) {
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

    public function testUpdatesRunButErrorPushing()
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
                    if ($cmd == $this->createExpectedCommandForPackage('psr/log')) {
                        file_put_contents("$dir/composer.lock", file_get_contents(__DIR__ . '/../fixtures/composer-psr-log.lock-updated'));
                    }
                    if ($cmd == 'git push origin psrlog100102 --force') {
                        $return = 1;
                    }
                    if (strpos($cmd, 'rm -rf /tmp/') === 0) {
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
        $this->assertOutputContainsMessage('Caught an exception: Could not push to psrlog100102', $c);
        $this->assertEquals(true, $called);
    }

    public function testEndToEnd()
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
                    if ($cmd == $this->createExpectedCommandForPackage('psr/log')) {
                        file_put_contents("$dir/composer.lock", file_get_contents(__DIR__ . '/../fixtures/composer-psr-log.lock-updated'));
                    }
                    if (strpos($cmd, 'rm -rf /tmp/') === 0) {
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
        $fake_pr_url = 'http://example.com/pr';
        $mock_provider->expects($this->once())
            ->method('createPullRequest')
            ->willReturn([
                'html_url' => $fake_pr_url,
            ]);
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
        $this->assertOutputContainsMessage($fake_pr_url, $c);
        $this->assertEquals(Message::PR_URL, $this->findMessage($fake_pr_url, $c)->getType());
        $this->assertEquals(true, $called);
    }

    public function testEndToEndCustomDescription()
    {
        $c = $this->cosy;
        $dir = $this->dir;
        $this->getMockOutputWithUpdate('psr/log', '1.0.0', '1.0.2');
        $this->placeComposerContentsFromFixture('composer-psr-log.json', $dir);
        $mock_executer = $this->createMock(CommandExecuter::class);
        $mock_executer->method('executeCommand')
            ->will($this->returnCallback(
                function ($cmd) use ($dir) {
                    if ($cmd == $this->createExpectedCommandForPackage('psr/log')) {
                        file_put_contents("$dir/composer.lock", file_get_contents(__DIR__ . '/../fixtures/composer-psr-log.lock-updated'));
                    }
                    return 0;
                }
            ));
        $c->setExecuter($mock_executer);
        $mock_provider_factory = $this->createMock(ProviderFactory::class);
        $mock_provider = $this->createMock(Github::class);
        $fake_pr_url = 'http://example.com/pr';
        $slug = new Slug();
        $slug->setProvider('github.com');
        $slug->setSlug('a/b');
        $mock_provider->expects($this->once())
            ->method('createPullRequest')
            ->with($slug, [
                'base' => 'master',
                'head' => 'psrlog100102',
                'title' => 'Update psr/log from 1.0.0 to 1.0.2',
                'body' => 'If you have a high test coverage index, and your tests for this pull request are passing, it should be both safe and recommended to merge this update.


***
a custom message
',
                'assignees' => [],
            ])
            ->willReturn([
                'html_url' => $fake_pr_url,
            ]);
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
        $this->placeComposerLockContentsFromFixture('composer-lock-private.lock', $dir);
        $project = new ProjectData();
        $project->setCustomPrMessage('a custom message');
        $c->setProject($project);
        $c->run();
        $this->assertOutputContainsMessage($fake_pr_url, $c);
        $this->assertEquals(Message::PR_URL, $this->findMessage($fake_pr_url, $c)->getType());
    }

    public function testEndToEndNotPrivate()
    {
        $dir = $this->dir;
        $c = $this->cosy;
        // Create a mock app, that can respond to things.
        $mock_output = $this->createMock(ArrayOutput::class);
        $mock_output->method('fetch')
            ->willReturn([
                [
                    $this->createUpdateJsonFromData('psr/log', '1.0.0', '1.0.2'),
                ]
            ]);
        $c->setOutput($mock_output);
        $this->placeComposerContentsFromFixture('composer-psr-log.json', $dir);
        $called = false;
        $mock_executer = $this->createMock(CommandExecuter::class);
        $mock_executer->method('executeCommand')
            ->will($this->returnCallback(
                function ($cmd) use (&$called, $dir) {
                    $return = 0;
                    if ($cmd == $this->createExpectedCommandForPackage('psr/log')) {
                        file_put_contents("$dir/composer.lock", file_get_contents(__DIR__ . '/../fixtures/composer-psr-log.lock-updated'));
                    }
                    if (strpos($cmd, 'rm -rf /tmp/') === 0) {
                        $called = true;
                    }
                    return $return;
                }
            ));
        $c->setExecuter($mock_executer);
        $this->assertEquals(false, $called);

        // Then we are going to mock the provider factory.
        $mock_provider_factory = $this->createMock(ProviderFactory::class);
        $mock_provider = $this->createMock(PublicGithubWrapper::class);
        $fake_pr_url = 'http://example.com/pr';
        $mock_provider->expects($this->once())
            ->method('createPullRequest')
            ->willReturn([
                'html_url' => $fake_pr_url,
            ]);
        $mock_provider->method('repoIsPrivate')
            ->willReturn(false);
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
        $c->setGithubAuth('test', 'pass');
        $c->run();
        $this->assertOutputContainsMessage($fake_pr_url, $c);
        $this->assertEquals(Message::PR_URL, $this->findMessage($fake_pr_url, $c)->getType());
        $this->assertEquals(true, $called);
    }

    public function testUpdatesFoundButNotSemverValidButStillAllowed()
    {
        $c = $this->cosy;
        $dir = $this->dir;
        $this->getMockOutputWithUpdate('psr/log', '1.0.0', '2.0.1');
        $this->placeComposerContentsFromFixture('composer-psr-log.json', $dir);
        $called = false;
        $mock_executer = $this->createMock(CommandExecuter::class);
        $install_called = false;
        $mock_executer->method('executeCommand')
            ->will($this->returnCallback(
                function ($cmd) use (&$called, &$install_called, $dir) {
                    if ($cmd == 'composer require -n --no-ansi psr/log:^2.0.1 --update-with-dependencies ') {
                        $install_called = true;
                        file_put_contents("$dir/composer.lock", file_get_contents(__DIR__ . '/../fixtures/composer-psr-log.lock-updated'));
                    }
                    if (strpos($cmd, 'rm -rf /tmp/') === 0) {
                        $called = true;
                    }
                    return 0;
                }
            ));
        $c->setExecuter($mock_executer);
        $this->assertEquals(false, $called);
        $this->registerProviderFactory($c);
        $this->assertEquals(false, $install_called);
        $this->placeComposerLockContentsFromFixture('composer-psr-log.lock', $dir);
        $c->run();
        $this->assertOutputContainsMessage('Creating pull request from psrlog100102', $c);
        $this->assertEquals(true, $called);
        $this->assertEquals(true, $install_called);
    }

    public function testEndToEndButNotUpdatedWithDependencies()
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
        $this->createComposerFileFromFixtures($dir, 'composer-psr-log-with-extra-update-with.json');
        $called = false;
        $mock_executer = $this->getMockExecuterWithReturnCallback(
            function ($cmd) use (&$called, $dir) {
                $return = 0;
                if ($cmd == 'composer update -n --no-ansi psr/log  ') {
                    file_put_contents("$dir/composer.lock", file_get_contents(__DIR__ . '/../fixtures/composer-psr-log.lock-updated'));
                }
                if (strpos($cmd, 'rm -rf /tmp/') === 0) {
                    $called = true;
                }
                return $return;
            }
        );
        $c->setExecuter($mock_executer);
        $this->assertEquals(false, $called);

        // Then we are going to mock the provider factory.
        $mock_provider_factory = $this->createMock(ProviderFactory::class);
        $mock_provider = $this->createMock(Github::class);
        $fake_pr_url = 'http://example.com/pr';
        $mock_provider->expects($this->once())
            ->method('createPullRequest')
            ->willReturn([
                'html_url' => $fake_pr_url,
            ]);
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
        $this->assertOutputContainsMessage($fake_pr_url, $c);
        $this->assertEquals(Message::PR_URL, $this->findMessage($fake_pr_url, $c)->getType());
        $this->assertEquals(true, $called);
    }

    public function testUpdatesAvailableConcurrentLimit()
    {
        $c = $this->getMockCosy();
        $dir = '/tmp/' . uniqid();
        $this->setupDirectory($c, $dir);
        $mock_definition = $this->getMockDefinition();
        $mock_app = $this->getMockApp($mock_definition);
        $c->setApp($mock_app);
        $mock_output = $this->createMock(ArrayOutput::class);
        $mock_output->method('fetch')
            ->willReturn([
                [
                    sprintf('{"installed": [
                        {"name": "%s", "version": "%s", "latest": "%s", "latest-status": "semver-safe-update"},
                        {"name": "%s", "version": "%s", "latest": "%s", "latest-status": "semver-safe-update"}
                    ]}', 'psr/log', '1.0.0', '1.0.3', 'violinist-dev/violinist-config', '1.0.0', '1.0.2'),
                ]
            ]);
        $c->setOutput($mock_output);
        $mock_executer = $this->getMockExecuterWithReturnCallback(
            function ($cmd) use ($dir) {
                $return = 0;
                $expected_command = $this->createExpectedCommandForPackage('psr/log');
                if ($cmd == $expected_command) {
                    file_put_contents("$dir/composer.lock", file_get_contents(__DIR__ . '/../fixtures/composer-concurrent.lock-updated'));
                }
                $expected_command = $this->createExpectedCommandForPackage('violinist-dev/violinist-config');
                if ($cmd == $expected_command) {
                    file_put_contents("$dir/composer.lock", file_get_contents(__DIR__ . '/../fixtures/composer-concurrent.lock-updated'));
                }
                return $return;
            }
        );
        $c->setExecuter($mock_executer);
        $this->createComposerFileFromFixtures($dir, 'composer-concurrent.json');
        // Then we are going to mock the provider factory.
        $mock_provider_factory = $this->createMock(ProviderFactory::class);
        $mock_provider = $this->createMock(Github::class);
        $fake_pr_url = 'http://example.com/pr';
        $slug = new Slug();
        $slug->setProvider('github.com');
        $slug->setSlug('a/b');
        $mock_provider->expects($this->once())
            ->method('createPullRequest')
            ->willReturn([
                'html_url' => $fake_pr_url,
            ]);
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
        $composer_lock_contents = file_get_contents(__DIR__ . '/../fixtures/composer-concurrent.lock');
        file_put_contents("$dir/composer.lock", $composer_lock_contents);
        $c->setProviderFactory($mock_provider_factory);
        $c->run();
        $message = $this->findMessage('Skipping violinist-dev/violinist-config because the number of max concurrent PRs (1) seems to have been reached', $c);
        $this->assertNotFalse($message);
    }

    public function testUpdateAvailableButUpdatedToOther()
    {
        $c = $this->getMockCosy();
        $dir = '/tmp/' . uniqid();
        $this->setupDirectory($c, $dir);
        $mock_definition = $this->getMockDefinition();
        $mock_app = $this->getMockApp($mock_definition);
        $c->setApp($mock_app);
        $mock_output = $this->createMock(ArrayOutput::class);
        $mock_output->method('fetch')
            ->willReturn([
                [
                    $this->createUpdateJsonFromData('drupal/core', '8.4.7', '8.5.4'),
                ]
            ]);
        $c->setOutput($mock_output);
        $this->createComposerFileFromFixtures($dir, 'composer-drupal-847.json');
        $called = false;
        $mock_executer = $this->getMockExecuterWithReturnCallback(
            function ($cmd) use (&$called, $dir) {
                $return = 0;
                $expected_command = $this->createExpectedCommandForPackage('drupal/core');
                if ($cmd == $expected_command) {
                    file_put_contents("$dir/composer.lock", file_get_contents(__DIR__ . '/../fixtures/composer-drupal-847-updated.lock'));
                }
                if (strpos($cmd, 'rm -rf /tmp/') === 0) {
                    $called = true;
                }
                return $return;
            }
        );
        $c->setExecuter($mock_executer);
        $this->assertEquals(false, $called);

        // Then we are going to mock the provider factory.
        $mock_provider_factory = $this->createMock(ProviderFactory::class);
        $mock_provider = $this->createMock(Github::class);
        $fake_pr_url = 'http://example.com/pr';
        $slug = new Slug();
        $slug->setProvider('github.com');
        $slug->setSlug('a/b');
        $mock_provider->expects($this->once())
            ->method('createPullRequest')
            ->with($slug, [
                'base' => 'master',
                'head' => 'drupalcore847848',
                'title' => 'Update drupal/core from 8.4.7 to 8.4.8',
                'body' => 'If you have a high test coverage index, and your tests for this pull request are passing, it should be both safe and recommended to merge this update.


***
This is an automated pull request from [Violinist](https://violinist.io/): Continuously and automatically monitor and update your composer dependencies. Have ideas on how to improve this message? All violinist messages are open-source, and [can be improved here](https://github.com/violinist-dev/violinist-messages).
',
                'assignees' => [],
            ])
            ->willReturn([
                'html_url' => $fake_pr_url,
            ]);
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
        $composer_lock_contents = file_get_contents(__DIR__ . '/../fixtures/composer-drupal-847.lock');
        file_put_contents("$dir/composer.lock", $composer_lock_contents);
        $c->run();
        $this->assertOutputContainsMessage($fake_pr_url, $c);
        $this->assertEquals(true, $called);
    }
}
