<?php

namespace eiriksm\CosyComposerTest\unit\Providers;

use Bitbucket\Api\Repositories\Workspaces;
use eiriksm\CosyComposer\ProviderInterface;
use eiriksm\CosyComposer\Providers\Bitbucket;
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
    /** @var list<string|null> */
    protected array $authenticateArguments = [];

    /** @var list<string|null> */
    protected array $authenticatePrivateArguments = [];

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

            case BitbucketProviderTest::class:
                $mock_repositories = $this->createMock(\Bitbucket\Api\Repositories::class);
                $mock_repositories->method('workspaces')
                    ->willReturn($mock_repo_api);
                $mock_client->expects($this->once())
                    ->method('repositories')
                    ->willReturn($mock_repositories);
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
            'mainbranch' => [
                'name' => 'master',
            ],
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
            ->method($this->getBranchesMethod());

        $mock_client = $this->getMockClient();
        $mock_response = [
            [
                'name' => 'master',
            ],
            [
                'name' => 'develop',
            ],
        ];
        switch (static::class) {
            case SelfHostedGitlabTest::class:
            case GitlabProviderTest::class:
                $mock_client->expects($this->once())
                    ->method('repositories')
                    ->willReturn($mock_repo_api);
                $expects = $expects->with("$user/$repo");
                break;

            case BitbucketProviderTest::class:
                $mock_refs = $this->createMock(Workspaces\Refs::class);
                $mock_refs->method('branches')
                    ->willReturn($mock_repo_api);
                $mock_workspace_api = $this->createMock(Workspaces::class);
                $mock_workspace_api->method('refs')
                    ->willReturn($mock_refs);
                $mock_repositories = $this->createMock(\Bitbucket\Api\Repositories::class);
                $mock_repositories->method('workspaces')
                    ->willReturn($mock_workspace_api);
                $mock_client->expects($this->once())
                    ->method('repositories')
                    ->willReturn($mock_repositories);
                $mock_response = [
                    'values' => $mock_response,
                ];
                break;

            default:
                $mock_client->expects($this->once())
                    ->method('api')
                    ->with('repo')
                    ->willReturn($mock_repo_api);
                $expects = $expects->with($user, $repo);
                break;
        }
        $expects->willReturn($mock_response);

        $mock_response = $this->createMock(ResponseInterface::class);
        $mock_response->method('getHeader')
            ->willReturn([]);
        switch (static::class) {
            case SelfHostedGitlabTest::class:
            case GitlabProviderTest::class:
                $mock_history = (new class {
                    private ?ResponseInterface $response = null;
                    public function getLastResponse() : ?ResponseInterface
                    {
                        return $this->response;
                    }
                    public function setResponse(ResponseInterface $response) : void
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
                            'merge_when_pipeline_succeeds' => true,
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
            ->method($this->getPrListMethod());
        $mock_pr_response = [
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
        ];
        /** @var \PHPUnit\Framework\MockObject\MockObject $mock_client */
        $mock_client = $this->getMockClient();
        switch (static::class) {
            case SelfHostedGitlabTest::class:
            case GitlabProviderTest::class:
                $expects = $expects->with("$user/$repo");
                break;

            case BitbucketProviderTest::class:
                $mock_pr_response = array_map(function ($item) {
                    $item['state'] = Bitbucket::MERGE_REQUEST_STATE_OPEN;
                    $item['destination'] = [
                        'commit' => [
                            'hash' => 'abab',
                        ],
                        'branch' => [
                            'name' => 'master',
                        ],
                    ];
                    $item['links'] = [
                        'html' => [
                            'href' => 'http://bitbucket.org/testUser/testRepo',
                        ],
                    ];
                    $item['id'] = uniqid();
                    $item['source'] = [
                        'branch' => [
                            'name' => $item['head']['ref'],
                        ],
                    ];
                    return $item;
                }, $mock_pr_response);
                $mock_pr_response = [
                    'values' => $mock_pr_response,
                ];
                $mock_workspace_api = $this->createMock(Workspaces::class);
                $mock_workspace_api->method('pullRequests')
                    ->willReturn($mock_pr);
                $mock_repositories = $this->createMock(\Bitbucket\Api\Repositories::class);
                $mock_repositories->method('workspaces')
                    ->willReturn($mock_workspace_api);
                $mock_client->method('repositories')
                    ->willReturn($mock_repositories);
                break;

            default:
                $expects = $expects->with($user, $repo);
                break;
        }

        $expects->willReturn($mock_pr_response);
        switch (static::class) {
            case SelfHostedGitlabTest::class:
            case GitlabProviderTest::class:
                $mock_repo = $this->createMock(Repositories::class);
                $mock_client->method('mergeRequests')
                    ->willReturn($mock_pr);
                $mock_client->method('repositories')
                    ->willReturn($mock_repo);
                break;

            case BitbucketProviderTest::class:
                break;

            default:
                $client_expects = $mock_client->expects($this->any());
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
                    private ?ResponseInterface $response = null;
                    public function getLastResponse() : ?ResponseInterface
                    {
                        return $this->response;
                    }
                    public function setResponse(ResponseInterface $response) : void
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
        /** @var ProviderInterface $provider */
        $provider = $this->getProvider($mock_client);
        $prs = $provider->getPrsNamed($slug);
        $named_array = $prs->getAllPrsNamed();
        $this->assertEquals(['patch-1', 'patch-2'], array_keys($named_array));
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

    /**
     * The method listing the branches, on the class from getRepoClassName('branches').
     */
    protected function getBranchesMethod()
    {
        return 'branches';
    }

    /**
     * The method listing the pull requests, on the class from getPrClassName().
     */
    protected function getPrListMethod()
    {
        return 'all';
    }
}
