<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\Message;
use eiriksm\CosyComposer\ProviderFactory;
use eiriksm\CosyComposer\Providers\Github;
use eiriksm\CosyComposer\Providers\PublicGithubWrapper;
use Github\Exception\RuntimeException;
use Violinist\ProjectData\ProjectData;

class ComposerUpdateUpdateTest extends ComposerUpdateIntegrationBase
{
    protected $commandWeAreLookingForCalled = false;

    public function testUpdatesFoundButProviderDoesNotAuthenticate()
    {
        $this->getMockOutputWithUpdate('eiriksm/fake-package', '1.0.0', '1.0.1');
        $composer_contents = '{"require": {"eiriksm/fake-package": "1.0.0"}}';
        $composer_file = $this->dir . "/composer.json";
        file_put_contents($composer_file, $composer_contents);
        // Then we are going to mock the provider factory.
        $mock_provider_factory = $this->createMock(ProviderFactory::class);
        $mock_provider = $this->createMock(Github::class);
        $mock_provider->method('authenticate')
            ->willThrowException(new RuntimeException('Bad credentials'));
        $mock_provider_factory->method('createFromHost')
            ->willReturn($mock_provider);

        $this->cosy->setProviderFactory($mock_provider_factory);
        $this->expectException(RuntimeException::class);
        $this->cosy->run();
    }

    public function testEndToEnd()
    {
        $this->composerAssetFiles = 'composer-psr-log';
        $this->packageForUpdateOutput = 'psr/log';
        $this->packageVersionForFromUpdateOutput = '1.0.0';
        $this->packageVersionForToUpdateOutput = '1.0.2';
        $this->checkPrUrl = true;
        $this->setUp();
        $this->runtestExpectedOutput();
    }

    public function testEndToEndCustomDescription()
    {
        $this->composerAssetFiles = 'composer-psr-log';
        $this->packageForUpdateOutput = 'psr/log';
        $this->packageVersionForFromUpdateOutput = '1.0.0';
        $this->packageVersionForToUpdateOutput = '1.0.2';
        $this->checkPrUrl = true;
        $this->setUp();
        $pr_params = [
            'base' => 'master',
            'head' => 'psrlog100102',
            'title' => 'Update psr/log from 1.0.0 to 1.0.2',
            'body' => 'If you have a high test coverage index, and your tests for this pull request are passing, it should be both safe and recommended to merge this update.

### Updated packages

Some times an update also needs new or updated dependencies to be installed. Even if this branch is for updating one dependency, it might contain other installs or updates. All of the updates in this branch can be found here:

- psr/log: 1.0.2#changed (updated from 1.0.2#4ebe3a8bf773a19edfe0a84b6585ba3d401b724d)



### Working with this branch

If you find you need to update the codebase to be able to merge this branch (for example update some tests or rebuild some assets), please note that violinist will force push to this branch to keep it up to date. This means you should not work on this branch directly, since you might lose your work. [Read more about branches created by violinist.io here](https://docs.violinist.io/#branches).

***
a custom message
',
            'assignees' => [],
        ];
        $project = new ProjectData();
        $project->setCustomPrMessage('a custom message');
        $this->cosy->setProject($project);
        $this->runtestExpectedOutput();
        self::assertEquals($pr_params, $this->prParams);
    }

    public function testEndToEndNotPrivate()
    {
        putenv('USE_GITHUB_PUBLIC_WRAPPER=true');
        $this->packageForUpdateOutput = 'psr/log';
        $this->packageVersionForFromUpdateOutput = '1.0.0';
        $this->packageVersionForToUpdateOutput = '1.0.2';
        $this->composerAssetFiles = 'composer-psr-log';
        $this->checkPrUrl = true;
        $this->setUp();

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

        $this->cosy->setProviderFactory($mock_provider_factory);
        $this->cosy->setAuthentication('pass');
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage($fake_pr_url, $this->cosy);
        $this->assertEquals(Message::PR_URL, $this->findMessage($fake_pr_url, $this->cosy)->getType());
    }

    public function testUpdatesFoundButNotSemverValidButStillAllowed()
    {
        $this->packageForUpdateOutput = 'psr/log';
        $this->packageVersionForFromUpdateOutput = '1.0.0';
        $this->packageVersionForToUpdateOutput = '2.0.1';
        $this->composerAssetFiles = 'composer-psr-log';
        $this->checkPrUrl = true;
        $this->setUp();
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage('Creating pull request from psrlog100102', $this->cosy);
        $this->assertEquals(true, $this->commandWeAreLookingForCalled);
    }

    public function testEndToEndButNotUpdatedWithDependencies()
    {
        $this->packageForUpdateOutput = 'psr/log';
        $this->packageVersionForFromUpdateOutput = '1.0.0';
        $this->packageVersionForToUpdateOutput = '1.0.2';
        $this->composerAssetFiles = 'composer-psr-log-with-extra-update-with';
        $this->checkPrUrl = true;
        $this->setUp();
        $this->runtestExpectedOutput();
    }

    public function testUpdateAvailableButUpdatedToOther()
    {
        $this->packageForUpdateOutput = 'drupal/core';
        $this->packageVersionForFromUpdateOutput = '8.4.7';
        $this->packageVersionForToUpdateOutput = '8.5.4';
        $this->composerAssetFiles = 'composer-drupal-847';
        $expected_pr = [
            'base' => 'master',
            'head' => 'drupalcore847848',
            'title' => 'Update drupal/core from 8.4.7 to 8.4.8',
            'body' => 'If you have a high test coverage index, and your tests for this pull request are passing, it should be both safe and recommended to merge this update.

### Updated packages

Some times an update also needs new or updated dependencies to be installed. Even if this branch is for updating one dependency, it might contain other installs or updates. All of the updates in this branch can be found here:

- drupal/core: 8.4.8 (updated from 8.4.7)



### Working with this branch

If you find you need to update the codebase to be able to merge this branch (for example update some tests or rebuild some assets), please note that violinist will force push to this branch to keep it up to date. This means you should not work on this branch directly, since you might lose your work. [Read more about branches created by violinist.io here](https://docs.violinist.io/#branches).

***
This is an automated pull request from [Violinist](https://violinist.io/): Continuously and automatically monitor and update your composer dependencies. Have ideas on how to improve this message? All violinist messages are open-source, and [can be improved here](https://github.com/violinist-dev/violinist-messages).
',
            'assignees' => [],
        ];
        $this->checkPrUrl = true;
        $this->setUp();
        $this->runtestExpectedOutput();
        self::assertEquals($expected_pr, $this->prParams);
    }

    public function testUpdatedAndNewInstalled()
    {
        $this->packageForUpdateOutput = 'drupal/core';
        $this->packageVersionForFromUpdateOutput = '8.8.0';
        $this->packageVersionForToUpdateOutput = '8.9.3';
        $this->composerAssetFiles = 'composer-drupal88';
        $this->checkPrUrl = true;
        $this->setUp();
        $this->runtestExpectedOutput();
        self::assertEquals([
            'base' => 'master',
            'head' => 'drupalcore880893',
            'title' => 'Update drupal/core from 8.8.0 to 8.9.3',
            'body' => 'If you have a high test coverage index, and your tests for this pull request are passing, it should be both safe and recommended to merge this update.

### Updated packages

Some times an update also needs new or updated dependencies to be installed. Even if this branch is for updating one dependency, it might contain other installs or updates. All of the updates in this branch can be found here:

- zendframework/zend-diactoros 1.8.7 (package was removed)
- zendframework/zend-escaper 2.6.1 (package was removed)
- zendframework/zend-feed 2.12.0 (package was removed)
- zendframework/zend-stdlib 3.2.1 (package was removed)
- drupal/core: 8.9.3 (updated from 8.8.0)
- laminas/laminas-diactoros: 1.8.7p2 (new package, previously not installed)
- laminas/laminas-escaper: 2.6.1 (new package, previously not installed)
- laminas/laminas-feed: 2.12.3 (new package, previously not installed)
- laminas/laminas-stdlib: 3.3.0 (new package, previously not installed)
- laminas/laminas-zendframework-bridge: 1.1.0 (new package, previously not installed)



### Working with this branch

If you find you need to update the codebase to be able to merge this branch (for example update some tests or rebuild some assets), please note that violinist will force push to this branch to keep it up to date. This means you should not work on this branch directly, since you might lose your work. [Read more about branches created by violinist.io here](https://docs.violinist.io/#branches).

***
This is an automated pull request from [Violinist](https://violinist.io/): Continuously and automatically monitor and update your composer dependencies. Have ideas on how to improve this message? All violinist messages are open-source, and [can be improved here](https://github.com/violinist-dev/violinist-messages).
',
            'assignees' => [],
        ], $this->prParams);
    }

    protected function handleExecutorReturnCallback($cmd, &$return)
    {
        if ($this->composerAssetFiles === 'composer-psr-log-with-extra-update-with') {
            if ($cmd == ['composer', 'update', '-n', '--no-ansi', 'psr/log']) {
                $this->placeComposerLockContentsFromFixture('composer-psr-log-with-extra-update-with.lock.updated', $this->dir);
            }
        }
        if ($cmd == ['composer', 'require', '-n', '--no-ansi', 'psr/log:^2.0.1', '--update-with-dependencies']) {
            $this->placeComposerLockContentsFromFixture('composer-psr-log.lock-updated', $this->dir);
            $this->commandWeAreLookingForCalled = true;
        }
    }
}
