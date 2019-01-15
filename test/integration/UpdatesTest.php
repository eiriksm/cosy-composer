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

class UpdatesTest extends Base
{
    private function createUpdateJsonFromData($package, $version, $new_version)
    {
        return sprintf('{"installed": [{"name": "%s", "version": "%s", "latest": "%s", "latest-status": "semver-safe-update"}]}', $package, $version, $new_version);
    }

    private function createExpectedCommandForPackageUpdate($package, $update_with_dependencies = true)
    {
        $with_deps = $update_with_dependencies ? '--with-dependencies' : '';
        return "COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_DISCARD_CHANGES=true composer --no-ansi --ignore-platform-reqs update -n --no-scripts $package $with_deps";
    }

    private function createExpectedCommandForPackageRequire($package, $constraint)
    {
        return "COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_DISCARD_CHANGES=true composer --no-ansi --ignore-platform-reqs require $package:$constraint --update-with-dependencies";
    }

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
        $mock_output = $this->createMock(ArrayOutput::class);
        $mock_output->method('fetch')
            ->willReturn([
                [
                    $this->createUpdateJsonFromData('eiriksm/fake-package', '1.0.0', '1.0.1'),
                ]
            ]);
        $c->setOutput($mock_output);
        $composer_contents = '{"require": {"drupal/core": "8.0.0"}}';
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
        $composer_contents = '{"require": {"drupal/core": "8.0.0"}}';
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
                ],
            ]);
        $mock_provider_factory->method('createFromHost')
            ->willReturn($mock_provider);

        $c->setProviderFactory($mock_provider_factory);
        $this->assertEquals(false, $called);
        $c->run();
        $output = $c->getOutput();
        $this->assertEquals(Message::PR_EXISTS, $output[11]->getType());
        $this->assertEquals('Skipping eiriksm/fake-package because a pull request already exists', $output[11]->getMessage());
        $this->assertEquals('eiriksm/fake-package', $output[11]->getContext()["package"]);
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
        $output = $c->getOutput();
        $this->assertEquals('Caught an exception: Did not find the requested package (eiriksm/fake-package) in the lockfile. This is probably an error', $output[11]->getMessage());
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
        $output = $c->getOutput();
        $this->assertEquals('Package psr/log with the constraint ^1.0 can not be updated to 2.0.1.', $output[11]->getMessage());
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
                    if ($cmd == $this->createExpectedCommandForPackageUpdate('psr/log')) {
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
        $output = $c->getOutput();
        $this->assertEquals('Caught an exception: Composer update did not complete successfully', $output[15]->getMessage());
        $this->assertEquals(true, $called);
        $this->assertEquals(true, $composer_update_called);
    }

    public function testNotUpdatedInComposerLock()
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
        $output = $c->getOutput();
        $this->assertEquals('psr/log was not updated running composer update', $output[14]->getMessage());
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
                    if ($cmd == $this->createExpectedCommandForPackageUpdate('psr/log')) {
                        file_put_contents("$dir/composer.lock", file_get_contents(__DIR__ . '/../fixtures/composer-psr-log.lock-updated'));
                    }
                    if ($cmd == 'GIT_AUTHOR_NAME="" GIT_AUTHOR_EMAIL="" GIT_COMMITTER_NAME="" GIT_COMMITTER_EMAIL="" git commit composer.* -m "Update psr/log"') {
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
        $output = $c->getOutput();
        $this->assertEquals('Caught an exception: Error committing the composer files. They are probably not changed.', $output[14]->getMessage());
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
                    if ($cmd == $this->createExpectedCommandForPackageUpdate('psr/log')) {
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
        $output = $c->getOutput();
        $this->assertEquals('Caught an exception: Could not push to psrlog100102', $output[14]->getMessage());
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
                    if ($cmd == $this->createExpectedCommandForPackageUpdate('psr/log')) {
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
        $output = $c->getOutput();
        $this->assertEquals($fake_pr_url, $output[17]->getMessage());
        $this->assertEquals(Message::PR_URL, $output[17]->getType());
        $this->assertEquals(true, $called);
    }

    public function testEndToEndNotPrivate()
    {
        $dir = '/tmp/' . uniqid();
        $c = $this->getMockCosy($dir);
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
                    if ($cmd == $this->createExpectedCommandForPackageUpdate('psr/log')) {
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
        $output = $c->getOutput();
        $this->assertEquals($fake_pr_url, $output[18]->getMessage());
        $this->assertEquals(Message::PR_URL, $output[18]->getType());
        $this->assertEquals(true, $called);
    }

    public function testUpdatesFoundButNotSemverValidButStillAllowed()
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
        $composer_contents = file_get_contents(__DIR__ . '/../fixtures/composer-psr-log.json');
        $composer_file = "$dir/composer.json";
        file_put_contents($composer_file, $composer_contents);
        $called = false;
        $mock_executer = $this->createMock(CommandExecuter::class);
        $install_called = false;
        $mock_executer->method('executeCommand')
            ->will($this->returnCallback(
                function ($cmd) use (&$called, &$install_called, $dir) {
                    $expected_command = $this->createExpectedCommandForPackageRequire('psr/log', '^2.0.1');
                    if ($cmd == $expected_command) {
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
        $this->assertEquals(false, $install_called);
        $composer_lock_contents = file_get_contents(__DIR__ . '/../fixtures/composer-psr-log.lock');
        file_put_contents("$dir/composer.lock", $composer_lock_contents);
        $c->run();
        $output = $c->getOutput();
        $this->assertEquals('Creating pull request from psrlog100102', $output[16]->getMessage());
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
                $expected_command = $this->createExpectedCommandForPackageUpdate('psr/log', false);
                if (strpos($cmd, $expected_command) === 0) {
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
        $output = $c->getOutput();
        $this->assertEquals($fake_pr_url, $output[17]->getMessage());
        $this->assertEquals(Message::PR_URL, $output[17]->getType());
        $this->assertEquals(true, $called);
    }

    public function testUpdateAvailableButUpdatedToOther()
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
                    $this->createUpdateJsonFromData('drupal/core', '8.4.7', '8.5.4'),
                ]
            ]);
        $c->setOutput($mock_output);
        $this->createComposerFileFromFixtures($dir, 'composer-drupal-847.json');
        $called = false;
        $mock_executer = $this->getMockExecuterWithReturnCallback(
            function ($cmd) use (&$called, $dir) {
                $return = 0;
                $expected_command = $this->createExpectedCommandForPackageUpdate('drupal/core');
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
        $mock_provider->expects($this->once())
            ->method('createPullRequest')
            ->with('a', 'b', [
                'base' => 'master',
                'head' => 'drupalcore847848',
                'title' => 'Update drupal/core from 8.4.7 to 8.4.8',
                'body' => 'If you have a decent test suite, and your tests pass, it should be both safe and smart to merge this update.


***
This is an automated pull request from [Violinist](https://violinist.io/): Continuously and automatically monitor and update your composer dependencies. Have ideas on how to improve this message? All violinist messages are open-source, and [can be improved here](https://github.com/violinist-dev/violinist-messages).
'
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
        $output = $c->getOutput();
        $this->assertEquals($fake_pr_url, $output[18]->getMessage());
        $this->assertEquals(true, $called);
    }
}
