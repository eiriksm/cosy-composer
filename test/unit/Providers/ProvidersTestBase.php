<?php

namespace eiriksm\CosyComposerTest\unit\Providers;

use eiriksm\CosyComposer\ProviderInterface;
use Github\Api\GraphQL;
use Github\Api\PullRequest;
use Github\Api\Repo;
use PHPUnit\Framework\MockObject\Builder\InvocationMocker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Violinist\Slug\Slug;

abstract class ProvidersTestBase extends TestCase implements TestProviderInterface
{
    /** @var list<string|null> */
    protected $authenticateArguments = [];

    /** @var list<string|null> */
    protected $authenticatePrivateArguments = [];

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
        $mock_show_api = $this->createMock($this->getRepoClassName('show'));
        $mock_show_api->expects($this->once())
            ->method('show')
            ->with(...$this->getShowArguments($user, $repo))
            ->willReturn($this->getShowResponse());
        $mock_client = $this->getMockClient();
        $this->configureShowClient($mock_client, $mock_show_api, $user, $repo);
        $provider = $this->getProvider($mock_client);
        $this->assertEquals('master', $provider->getDefaultBranch($slug));
    }

    public function testBranches()
    {
        $slug = Slug::createFromUrl('http://github.com/testUser/testRepo');
        $user = $slug->getUserName();
        $repo = $slug->getUserRepo();
        $mock_branches_api = $this->createMock($this->getRepoClassName('branches'));
        $mock_branches_api->expects($this->once())
            ->method($this->getBranchesMethod())
            ->with(...$this->getBranchesArguments($user, $repo))
            ->willReturn($this->getBranchesResponse([
                [
                    'name' => 'master',
                ],
                [
                    'name' => 'develop',
                ],
            ]));
        $mock_client = $this->getMockClient();
        $this->configureBranchesClient($mock_client, $mock_branches_api, $user, $repo);
        $this->configureLastResponse($mock_client);
        $provider = $this->getProvider($mock_client);
        $this->assertEquals(['master', 'develop'], $provider->getBranchesFlattened($slug));
    }

    public function testAutomerge()
    {
        $slug = Slug::createFromUrl('http://github.com/testUser/testRepo');
        $mock_client = $this->getMockClient();
        $this->configureAutomergeClient($mock_client);
        $provider = $this->getProvider($mock_client);
        $result = $provider->enableAutomerge([
            'node_id' => 12345,
            'number' => 12345,
        ], $slug);
        self::assertSame($this->getExpectedAutomergeResult(), $result);
    }

    public function testPrsNamed()
    {
        $slug = Slug::createFromUrl('http://github.com/testUser/testRepo');
        $user = 'testUser';
        $repo = 'testRepo';
        $mock_prs_api = $this->createMock($this->getPrClassName());
        $mock_prs_api->expects($this->once())
            ->method($this->getPrListMethod())
            ->with(...$this->getPrsArguments($user, $repo))
            ->willReturn($this->getPrsResponse($this->getPrsFixture()));
        $mock_client = $this->getMockClient();
        $this->configurePrsClient($mock_client, $mock_prs_api, $user, $repo);
        $this->configureLastResponse($mock_client);
        $provider = $this->getProvider($mock_client);
        $prs = $provider->getPrsNamed($slug);
        $this->assertEquals(['patch-1', 'patch-2'], array_keys($prs->getAllPrsNamed()));
        $this->assertEquals($this->getExpectedKnownPackages(), $prs->getKnownPackageNames());
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
    protected function getBranchesMethod() : string
    {
        return 'branches';
    }

    /**
     * The method listing the pull requests, on the class from getPrClassName().
     */
    protected function getPrListMethod() : string
    {
        return 'all';
    }

    /**
     * Points the client at the api object showing a single repo.
     */
    protected function configureShowClient(MockObject $client, MockObject $show_api, string $user, string $repo) : void
    {
        $client->expects($this->once())
            ->method('api')
            ->willReturn($show_api);
    }

    /**
     * The arguments the show method is expected to be called with.
     *
     * @return list<string>
     */
    protected function getShowArguments(string $user, string $repo) : array
    {
        return [$user, $repo];
    }

    /**
     * The repo data returned by the show method, with master as default branch.
     *
     * @return array<string, mixed>
     */
    protected function getShowResponse() : array
    {
        return [
            'default_branch' => 'master',
        ];
    }

    /**
     * Points the client at the api object listing the branches.
     */
    protected function configureBranchesClient(
        MockObject $client,
        MockObject $branches_api,
        string $user,
        string $repo
    ) : void {
        $client->expects($this->once())
            ->method('api')
            ->with('repo')
            ->willReturn($branches_api);
    }

    /**
     * The arguments the branches method is expected to be called with.
     *
     * @return list<string>
     */
    protected function getBranchesArguments(string $user, string $repo) : array
    {
        return [$user, $repo];
    }

    /**
     * Shapes the branch list the way this provider returns it.
     *
     * @param list<array<string, mixed>> $branches
     *
     * @return array<mixed>
     */
    protected function getBranchesResponse(array $branches) : array
    {
        return $branches;
    }

    /**
     * Stubs the response the provider paginates with, without any next page.
     */
    protected function configureLastResponse(MockObject $client) : void
    {
        $mock_response = $this->createMock(ResponseInterface::class);
        $mock_response->method('getHeader')
            ->willReturn([]);
        $client->expects($this->once())
            ->method('getLastResponse')
            ->willReturn($mock_response);
    }

    /**
     * Stubs whatever api the provider merges through.
     */
    protected function configureAutomergeClient(MockObject $client) : void
    {
        $mock_graphql = $this->createMock(GraphQL::class);
        $mock_graphql->method('execute')
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
        $client->method('api')
            ->willReturn($mock_graphql);
    }

    /**
     * Whether this provider supports enabling automerge at all.
     */
    protected function getExpectedAutomergeResult() : bool
    {
        return true;
    }

    /**
     * Points the client at the api object listing the pull requests.
     */
    protected function configurePrsClient(MockObject $client, MockObject $prs_api, string $user, string $repo) : void
    {
        $client->expects($this->any())
            ->method('api')
            ->with($this->getPrApiMethod())
            ->willReturn($prs_api);
    }

    /**
     * The arguments the pull request listing method is expected to be called with.
     *
     * @return list<string>
     */
    protected function getPrsArguments(string $user, string $repo) : array
    {
        return [$user, $repo];
    }

    /**
     * Two open pull requests, in the shape the github api returns them.
     *
     * @return list<array<string, mixed>>
     */
    protected function getPrsFixture() : array
    {
        return [
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
    }

    /**
     * Shapes the pull request list the way this provider returns it.
     *
     * @param list<array<string, mixed>> $prs
     *
     * @return array<mixed>
     */
    protected function getPrsResponse(array $prs) : array
    {
        return $prs;
    }

    /**
     * The packages the provider is expected to recognize from the pr commits.
     *
     * @return list<string>
     */
    protected function getExpectedKnownPackages() : array
    {
        return [];
    }
}
