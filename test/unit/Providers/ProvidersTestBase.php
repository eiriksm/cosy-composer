<?php

namespace eiriksm\CosyComposerTest\unit\Providers;

use eiriksm\CosyComposer\ProviderInterface;
use Github\Api\PullRequest;
use Github\Api\Repo;
use Gitlab\Api\Repositories;
use Gitlab\HttpClient\Plugin\History;
use PHPUnit\Framework\MockObject\Builder\InvocationMocker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

abstract class ProvidersTestBase extends TestCase implements TestProviderInterface
{
    protected $authenticateArguments = [];

    public function testAuthenticate()
    {
        $client = $this->getMockClient();
        $expect = $client->expects($this->once())
            ->method('authenticate');
        $this->configureArguments('authenticateArguments', $expect);
        $provider = $this->getProvider($client);
        $this->runAuthenticate($provider);
    }

    public function testAuthenticatePrivate()
    {
        $mock_client = $this->getMockClient();
        $expect = $mock_client->expects($this->once())
            ->method('authenticate');
        $this->configureArguments('authenticatePrivateArguments', $expect);
        $provider = $this->getProvider($mock_client);
        $this->runAuthenticate($provider, 'authenticatePrivate');
    }

    public function testDefaultBranch()
    {
        $user = 'testUser';
        $repo = 'testRepo';
        $mock_repo_api = $this->createMock($this->getRepoClassName('show'));
        $expects = $mock_repo_api->expects($this->once())
            ->method('show');
        switch (static::class) {
            case SelfHostedGitlabTest::class:
            case GitlabProviderTest::class:
                $expects = $expects->with("$user/$repo");
                break;

            default:
                $expects = $expects->with($user, $repo);
                break;
        }

        $expects->willReturn([
            'default_branch' => 'master',
        ]);
        $mock_client = $this->getMockClient();
        $mock_client->expects($this->once())
            ->method('api')
            ->with($this->getBranchMethod())
            ->willReturn($mock_repo_api);
        $provider = $this->getProvider($mock_client);
        $this->assertEquals('master', $provider->getDefaultBranch($user, $repo));
    }

    public function testBranches()
    {
        $user = 'testUser';
        $repo = 'testRepo';
        $mock_repo_api = $this->createMock($this->getRepoClassName('branches'));
        $expects = $mock_repo_api->expects($this->once())
            ->method('branches');
        switch (static::class) {
            case SelfHostedGitlabTest::class:
            case GitlabProviderTest::class:
                $expects = $expects->with("$user/$repo");
                break;

            default:
                $expects = $expects->with($user, $repo);
                break;
        }
        $expects->willReturn([
                [
                    'name' => 'master',
                ],
                [
                    'name' => 'develop',
                ],
            ]);
        $mock_client = $this->getMockClient();
        $mock_client->expects($this->once())
            ->method('api')
            ->with('repo')
            ->willReturn($mock_repo_api);
        $mock_response = $this->createMock(ResponseInterface::class);
        switch (static::class) {
            case SelfHostedGitlabTest::class:
            case GitlabProviderTest::class:
                $mock_history = $this->createMock(History::class);
                $mock_history->expects($this->once())
                    ->method('getLastResponse')
                    ->willReturn($mock_response);
                $mock_client->expects($this->once())
                    ->method('getResponseHistory')
                    ->willReturn($mock_history);
                break;

            default:
                $mock_client->expects($this->once())
                    ->method('getLastResponse')
                    ->willReturn($mock_response);
                break;
        }
        $provider = $this->getProvider($mock_client);
        $this->assertEquals(['master', 'develop'], $provider->getBranchesFlattened($user, $repo));
    }

    public function testPrsNamed()
    {
        $user = 'testUser';
        $repo = 'testRepo';
        $mock_pr = $this->createMock($this->getPrClassName());
        $expects = $mock_pr->expects($this->once())
            ->method('all');
        switch (static::class) {
            case SelfHostedGitlabTest::class:
            case GitlabProviderTest::class:
                $expects = $expects->with("$user/$repo");
                break;

            default:
                $expects = $expects->with($user, $repo);
                break;
        }

        $expects->willReturn([
                [
                    'head' => [
                        'ref' => 'patch-1',
                    ],
                    'state' => 'opened',
                    'source_branch' => 'patch-1',
                    'title' => 'Patch 1',
                    'iid' => 123,
                    'sha' => 'abab',
                ],
                [
                    'head' => [
                        'ref' => 'patch-2',
                    ],
                    'state' => 'opened',
                    'source_branch' => 'patch-2',
                    'title' => 'Patch 2',
                    'iid' => 456,
                    'sha' => 'fefe',
                ],
            ]);
        /** @var MockObject $mock_client */
        $mock_client = $this->getMockClient();
        switch (static::class) {
            case SelfHostedGitlabTest::class:
            case GitlabProviderTest::class:
                $client_expects = $mock_client->expects($this->exactly(3));
                $mock_repo = $this->createMock(Repositories::class);
                $client_expects->method('api')
                    ->willReturnCallback(function ($method) use ($mock_pr, $mock_repo) {
                        if ($method == 'mr') {
                            return $mock_pr;
                        }
                        return $mock_repo;
                    });
                break;

            default:
                $client_expects = $mock_client->expects($this->once());
                $client_expects->method('api')
                    ->with($this->getPrApiMethod())
                    ->willReturn($mock_pr);
                break;
        }
        $mock_response = $this->createMock(ResponseInterface::class);
        switch (static::class) {
            case SelfHostedGitlabTest::class:
            case GitlabProviderTest::class:
                $mock_history = $this->createMock(History::class);
                $mock_history->expects($this->once())
                    ->method('getLastResponse')
                    ->willReturn($mock_response);
                $mock_client->expects($this->once())
                    ->method('getResponseHistory')
                    ->willReturn($mock_history);
                break;

            default:
                $mock_client->expects($this->once())
                    ->method('getLastResponse')
                    ->willReturn($mock_response);
                break;
        }
        $provider = $this->getProvider($mock_client);
        $this->assertEquals(['patch-1', 'patch-2'], array_keys($provider->getPrsNamed($user, $repo)));
    }

    protected function configureArguments($key, InvocationMocker $object)
    {
        $arguments = $this->{$key};
        switch (count($arguments)) {
            case 2:
                list($one, $two) = $arguments;
                $object->with($one, $two);
                break;

            case 3:
                list($one, $two, $three) = $arguments;
                $object->with($one, $two, $three);
                break;

            default:
                throw new \Exception('Auth arguments not configured');
        }
    }

    protected function runAuthenticate(ProviderInterface $provider, $method = 'authenticate')
    {
        $user = 'testUser';
        $password = 'testPassword';
        $provider->{$method}($user, $password);
    }

    protected function getPrData()
    {
        return [
            'testUser',
            'testRepo',
            [
                'param1' => true,
            ],
        ];
    }

    protected function getRepoClassName($context)
    {
        return Repo::class;
    }

    protected function getPrClassName()
    {
        return PullRequest::class;
    }

    protected function getPrApiMethod()
    {
        return 'pr';
    }
}
