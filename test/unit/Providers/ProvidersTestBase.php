<?php

namespace eiriksm\CosyComposerTest\unit\Providers;

use eiriksm\CosyComposer\ProviderInterface;
use Github\Api\GraphQL;
use Github\Api\PullRequest;
use Github\Api\Repo;
use Gitlab\Api\Repositories;
use PHPUnit\Framework\MockObject\Builder\InvocationMocker;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Violinist\Slug\Slug;

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
        $slug = Slug::createFromUrl('http://github.com/testUser/testRepo');
        $user = $slug->getUserName();
        $repo = $slug->getUserRepo();
        $mock_repo_api = $this->createMock($this->getRepoClassName('show'));
        $expects = $mock_repo_api->expects($this->once())
            ->method('show');
        $mock_client = $this->getMockClient();
        switch (static::class) {
            case SelfHostedGitlabTest::class:
            case GitlabProviderTest::class:
                $expects = $expects->with("$user/$repo");
                $mock_client->expects($this->once())
                    ->method('projects')
                    ->willReturn($mock_repo_api);
                break;

            default:
                $mock_client->expects($this->once())
                    ->method('api')
                    ->willReturn($mock_repo_api);
                $expects = $expects->with($user, $repo);
                break;
        }

        $expects->willReturn([
            'default_branch' => 'master',
        ]);

        $provider = $this->getProvider($mock_client);
        $this->assertEquals('master', $provider->getDefaultBranch($slug));
    }

    public function testBranches()
    {
        $slug = Slug::createFromUrl('http://github.com/testUser/testRepo');
        $user = $slug->getUserName();
        $repo = $slug->getUserRepo();
        $mock_repo_api = $this->createMock($this->getRepoClassName('branches'));
        $expects = $mock_repo_api->expects($this->once())
            ->method('branches');

        $mock_client = $this->getMockClient();
        switch (static::class) {
            case SelfHostedGitlabTest::class:
            case GitlabProviderTest::class:
                $mock_client->expects($this->once())
                    ->method('repositories')
                    ->willReturn($mock_repo_api);
                $expects = $expects->with("$user/$repo");
                break;

            default:
                $mock_client->expects($this->once())
                    ->method('api')
                    ->with('repo')
                    ->willReturn($mock_repo_api);
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

        $mock_response = $this->createMock(ResponseInterface::class);
        $mock_response->method('getHeader')
            ->willReturn([]);
        switch (static::class) {
            case SelfHostedGitlabTest::class:
            case GitlabProviderTest::class:
                $mock_history = (new class {
                    private $response;
                    public function getLastResponse()
                    {
                        return $this->response;
                    }
                    public function setResponse(ResponseInterface $response)
                    {
                        $this->response = $response;
                    }
                });
                $mock_history->setResponse($mock_response);
                break;

            default:
                $mock_client->expects($this->once())
                    ->method('getLastResponse')
                    ->willReturn($mock_response);
                break;
        }
        $provider = $this->getProvider($mock_client);
        $this->assertEquals(['master', 'develop'], $provider->getBranchesFlattened($slug));
    }

    public function testAutomerge()
    {
        $slug = Slug::createFromUrl('http://github.com/testUser/testRepo');
        $mock_client = $this->getMockClient();
        $mock_pr = $this->createMock($this->getPrClassName());
        $merge_params = [];
        switch (static::class) {
            case SelfHostedGitlabTest::class:
            case GitlabProviderTest::class:
                $mock_pr->method('merge')
                    ->willReturnCallback(function ($project_id, $mr_id, $data) use (&$merge_params) {
                        $merge_params = $data;
                        return [
                            'merge_when_pipeline_succeeds' => TRUE,
                        ];
                    });
                $mock_client->method('mergeRequests')
                    ->willReturn($mock_pr);
                break;

            case GithubProviderTest::class:
                $mock_g = $this->createMock(GraphQL::class);
                $mock_g->method('execute')
                    ->willReturn([
                        'data' => [
                            'repository' => [
                                'pullRequest' => [
                                    'merge' => [
                                        'pullRequest' => [
                                            'state' => 'MERGED',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ]);
                $mock_client->method('api')
                    ->willReturn($mock_g);
                break;
        }
        $provider = $this->getProvider($mock_client);
        $result = $provider->enableAutomerge([
            'node_id' => 12345,
            'number' => 12345,
        ], $slug);
        self::assertEquals(true, $result);
    }

    public function testPrsNamed()
    {
        $slug = Slug::createFromUrl('http://github.com/testUser/testRepo');
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
                    'target_branch' => 'master',
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
                    'target_branch' => 'master',
                    'title' => 'Patch 2',
                    'iid' => 456,
                    'sha' => 'fefe',
                ],
            ]);
        /** @var \PHPUnit\Framework\MockObject\MockObject $mock_client */
        $mock_client = $this->getMockClient();
        switch (static::class) {
            case SelfHostedGitlabTest::class:
            case GitlabProviderTest::class:
                $mock_repo = $this->createMock(Repositories::class);
                $mock_client->method('mergeRequests')
                    ->willReturn($mock_pr);
                $mock_client->method('repositories')
                    ->willReturn($mock_repo);
                break;

            default:
                $client_expects = $mock_client->expects($this->once());
                $client_expects->method('api')
                    ->with($this->getPrApiMethod())
                    ->willReturn($mock_pr);
                break;
        }
        $mock_response = $this->createMock(ResponseInterface::class);
        $mock_response->method('getHeader')
            ->willReturn([]);
        switch (static::class) {
            case SelfHostedGitlabTest::class:
            case GitlabProviderTest::class:
                $mock_history = (new class {
                    private $response;
                    public function getLastResponse()
                    {
                        return $this->response;
                    }
                    public function setResponse(ResponseInterface $response)
                    {
                        $this->response = $response;
                    }
                });
                $mock_history->setResponse($mock_response);
                break;

            default:
                $mock_client->expects($this->once())
                    ->method('getLastResponse')
                    ->willReturn($mock_response);
                break;
        }
        $provider = $this->getProvider($mock_client);
        $this->assertEquals(['patch-1', 'patch-2'], array_keys($provider->getPrsNamed($slug)));
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
