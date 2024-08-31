<?php

namespace eiriksm\CosyComposerTest\integration\issues;

use Bitbucket\Api\Repositories;
use Bitbucket\Client;
use eiriksm\CosyComposer\CommandExecuter;
use eiriksm\CosyComposer\CosyComposer;
use eiriksm\CosyComposer\ProviderFactory;
use eiriksm\CosyComposer\Providers\Bitbucket;
use eiriksm\CosyComposerTest\integration\ComposerUpdateIntegrationBase;

/**
 * Class Issue158Test.
 */
class Issue158Test extends ComposerUpdateIntegrationBase
{
    protected $composerAssetFiles = 'composer-default_branch';
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.0.2';
    protected $packageVersionForToUpdateOutput = '1.1.3';

    public function testIssue158()
    {
        $this->getMockOutputWithUpdate('psr/log', '1.0.2', '1.1.3');
        $mock_provider_factory = $this->createMock(ProviderFactory::class);
        $mock_client = $this->createMock(Client::class);
        $provider = new Bitbucket($mock_client);
        $mock_repo = $this->createMock(Repositories::class);
        $mock_prs = $this->createMock(Repositories\Workspaces\PullRequests::class);
        $mock_refs = $this->createMock(Repositories\Workspaces\Refs::class);
        $mock_branches = $this->createMock(Repositories\Workspaces\Refs\Branches::class);
        $mock_refs->method('branches')
            ->willReturn($mock_branches);
        // If using the v4 of the library, we do not have to mock this method.
        $reflected_client = new \ReflectionClass(Client::class);
        $const = $reflected_client->getConstant('USER_AGENT');
        $is_version_3 = strpos($const, 'bitbucket-php-api-client/3') === 0;
        if ($is_version_3) {
            $mock_branches->method('perPage')
                ->willReturn($mock_branches);
        }
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
        $mock_workspaces = $this->createMock(Repositories\Workspaces::class);
        $mock_workspaces->method('pullRequests')
            ->willReturn($mock_prs);
        if ($is_version_3) {
            $mock_prs->method('perPage')
                ->willReturn($mock_prs);
        }
        $mock_repo->method('workspaces')
            ->willReturn($mock_workspaces);
        $mock_workspaces->method('refs')
            ->willReturn($mock_refs);
        $mock_workspaces->method('show')
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
        $mock_client->method('repositories')
            ->willReturn($mock_repo);
        $mock_provider_factory->method('createFromHost')
            ->willReturn($provider);
        $this->cosy->setProviderFactory($mock_provider_factory);
        $this->cosy->run();
        $this->assertTrue($correct_params);
    }
}
