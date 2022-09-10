<?php

namespace eiriksm\CosyComposerTest\integration\issues;

use eiriksm\CosyComposer\CommandExecuter;
use eiriksm\CosyComposer\Message;
use eiriksm\CosyComposer\ProviderFactory;
use eiriksm\CosyComposer\Providers\Github;
use eiriksm\CosyComposerTest\GetCosyTrait;
use eiriksm\CosyComposerTest\integration\Base;

/**
 * Class Issue90Test.
 *
 * Issue 90 was the fact that after we switched the updating to the updater package, the changelogs might be empty,
 * since we did not read the "after-lock-data" in the runner class.
 */
class Issue90Test extends Base
{
    use GetCosyTrait;

    public function testChangelogCalledWithReference()
    {
        $c = $this->getMockCosy();
        $dir = '/tmp/' . uniqid();
        $this->setupDirectory($c, $dir);
        $definition = $this->getMockDefinition();
        $mock_app = $this->getMockApp($definition);
        $c->setApp($mock_app);
        $mock_output = $this->getMockOutputWithUpdate('psr/log', '1.0.0', '1.0.2');
        $c->setOutput($mock_output);
        $this->placeComposerContentsFromFixture('composer-psr-log.json', $dir);
        $mock_executer = $this->createMock(CommandExecuter::class);
        $called_one_line_correctly = false;
        $mock_executer->method('executeCommand')
            ->will($this->returnCallback(
                function ($cmd) use ($dir, &$called_one_line_correctly) {
                    if ($cmd == $this->createExpectedCommandForPackage('psr/log')) {
                        file_put_contents("$dir/composer.lock", file_get_contents(__DIR__ . '/../../fixtures/composer-psr-log.lock-updated'));
                    }
                    if ($cmd === ["git", '-C', '/tmp/e9a8b66d7a4bac57a08b8f0f2664c50f', 'log', '4ebe3a8bf773a19edfe0a84b6585ba3d401b724d..changed', "--oneline"]) {
                        $called_one_line_correctly = true;
                    }
                    return 0;
                }
            ));
        $c->setExecuter($mock_executer);
        $this->assertEquals(false, $called_one_line_correctly);

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
        $composer_lock_contents = file_get_contents(__DIR__ . '/../../fixtures/composer-psr-log.lock');
        file_put_contents("$dir/composer.lock", $composer_lock_contents);
        $c->run();
        $this->assertEquals(true, $called_one_line_correctly);
    }
}
