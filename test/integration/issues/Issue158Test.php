<?php

namespace eiriksm\CosyComposerTest\integration\issues;

use Bitbucket\Api\Repositories;
use Bitbucket\Client;
use eiriksm\CosyComposer\CommandExecuter;
use eiriksm\CosyComposer\CosyComposer;
use eiriksm\CosyComposer\ProviderFactory;
use eiriksm\CosyComposer\Providers\Bitbucket;
use eiriksm\CosyComposer\Providers\Github;
use eiriksm\CosyComposerTest\integration\Base;

/**
 * Class Issue98Test.
 *
 * Issue 98 was that after we switched the change log fetcher, we forgot to set the auth on the fetcher, so private
 * repos were not fetched with auth tokens set.
 */
class Issue158Test extends Base
{
    public function testIssue98()
    {
        if (version_compare(phpversion(), "7.1.0", "<=")) {
            $this->assertTrue(true, 'Skipping bitbucket test for version ' . phpversion());
            return;
        }
        $c = $this->cosy;
        $dir = $this->dir;
        $this->getMockOutputWithUpdate('psr/log', '1.0.2', '1.1.3');
        $this->placeComposerContentsFromFixture('composer-default_branch.json', $dir);
        $mock_executer = $this->createMock(CommandExecuter::class);
        $mock_executer->method('executeCommand')
            ->will($this->returnCallback(
                function ($cmd) use ($dir) {
                    if ($cmd == $this->createExpectedCommandForPackage('psr/log')) {
                        $this->placeComposerLockContentsFromFixture('composer-default_branch.lock.updated', $dir);
                    }
                    return 0;
                }
            ));
        $c->setExecuter($mock_executer);
        $this->placeComposerLockContentsFromFixture('composer-default_branch.lock', $dir);
        $mock_provider_factory = $this->createMock(ProviderFactory::class);
        $mock_client = $this->createMock(Client::class);
        $provider = new Bitbucket($mock_client);
        $mock_repo = $this->createMock(Repositories::class);
        $mock_users = $this->createMock(Repositories\Users::class);
        $mock_prs = $this->createMock(Repositories\Users\PullRequests::class);
        $mock_users->method('pullRequests')
            ->willReturn($mock_prs);
        $mock_refs = $this->createMock(Repositories\Users\Refs::class);
        $mock_branches = $this->createMock(Repositories\Users\Refs\Branches::class);
        $mock_refs->method('branches')
            ->willReturn($mock_branches);
        $mock_branches->method('list')
            ->willReturn([
                'values' => [
                    [
                        'name' => 'master',
                        'target' => [
                            'hash' => 'ababab',
                        ],
                    ],
                ],
            ]);
        $mock_users->method('refs')
            ->willReturn($mock_refs);
        $mock_users->method('show')
            ->willReturn([
                'is_private' => true,
                'mainbranch' => [
                    'name' => 'master',
                ],
            ]);
        $correct_params = false;
        $mock_prs->method('list')
            ->willReturn([
                'values' => [],
            ]);
        $mock_prs->method('create')
            ->willReturnCallback(function ($params) use (&$correct_params) {
                if ($params["destination"]["branch"]["name"] === 'develop') {
                    $correct_params = true;
                }
            });
        $mock_repo->method('users')
            ->willReturn($mock_users);
        $mock_client->method('repositories')
            ->willReturn($mock_repo);
        $mock_provider_factory->method('createFromHost')
            ->willReturn($provider);
        /** @var CosyComposer $c */
        $c->setProviderFactory($mock_provider_factory);
        $c->run();
        $this->assertTrue($correct_params);
    }
}
