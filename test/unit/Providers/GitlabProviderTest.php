<?php

namespace eiriksm\CosyComposerTest\unit\Providers;

use eiriksm\CosyComposer\ProviderInterface;
use eiriksm\CosyComposer\Providers\Gitlab;
use Gitlab\Api\MergeRequests;
use Gitlab\Api\Projects;
use Gitlab\Api\Repositories;
use Gitlab\Client;
use PHPUnit\Framework\MockObject\MockObject;
use Violinist\Slug\Slug;

class GitlabProviderTest extends ProvidersTestBase
{
    /** @var list<string|null> */
    protected $authenticateArguments = [
        'testUser',
        Client::AUTH_OAUTH_TOKEN,
    ];

    /** @var list<string|null> */
    protected $authenticatePrivateArguments = [
        'testUser',
        Client::AUTH_OAUTH_TOKEN,
    ];

    public function testRepoIsPrivate()
    {
        $slug = Slug::createFromUrl('http://gitlab.com/testUser/testRepo');
        $client = $this->getMockClient();
        $provider = $this->getProvider($client);
        $this->assertEquals(true, $provider->repoIsPrivate($slug));
    }

    public function testDefaultBaseTimestamp(): void
    {
        $slug = Slug::createFromUrl('http://gitlab.com/testUser/testRepo');
        $mock_repo_api = $this->createMock(Repositories::class);
        $mock_repo_api->expects($this->once())
            ->method('branches')
            ->with('testUser/testRepo')
            ->willReturn([
                [
                    'name' => 'main',
                    'commit' => [
                        'id' => 'abcd',
                        'committed_date' => '2025-01-15T10:30:00.000+00:00',
                    ],
                ],
            ]);
        $mock_client = $this->getMockClient();
        $mock_client->expects($this->once())
            ->method('repositories')
            ->willReturn($mock_repo_api);
        $mock_response = $this->createMock(\Psr\Http\Message\ResponseInterface::class);
        $mock_response->method('getHeader')
            ->willReturn([]);
        $provider = new Gitlab($mock_client);
        $this->assertEquals('2025-01-15T10:30:00.000+00:00', $provider->getDefaultBaseTimestamp($slug, 'main'));
    }

    public function testDefaultBaseTimestampReturnsNullForMissingBranch(): void
    {
        $slug = Slug::createFromUrl('http://gitlab.com/testUser/testRepo');
        $mock_repo_api = $this->createMock(Repositories::class);
        $mock_repo_api->expects($this->once())
            ->method('branches')
            ->with('testUser/testRepo')
            ->willReturn([
                [
                    'name' => 'other',
                    'commit' => [
                        'id' => 'abcd',
                        'committed_date' => '2025-01-15T10:30:00.000+00:00',
                    ],
                ],
            ]);
        $mock_client = $this->getMockClient();
        $mock_client->expects($this->once())
            ->method('repositories')
            ->willReturn($mock_repo_api);
        $mock_response = $this->createMock(\Psr\Http\Message\ResponseInterface::class);
        $mock_response->method('getHeader')
            ->willReturn([]);
        $provider = new Gitlab($mock_client);
        $this->assertNull($provider->getDefaultBaseTimestamp($slug, 'main'));
    }

    public function getProvider(object $client) : ProviderInterface
    {
        return new Gitlab($client);
    }

    public function getMockClient()
    {
        return $this->createMock(Client::class);
    }

    protected function getRepoClassName($context)
    {
        if ($context === 'branches') {
            return Repositories::class;
        }
        return Projects::class;
    }

    protected function getPrClassName()
    {
        return MergeRequests::class;
    }

    protected function getPrApiMethod()
    {
        return 'mr';
    }

    protected function configureShowClient(MockObject $client, MockObject $show_api, string $user, string $repo) : void
    {
        $client->expects($this->once())
            ->method('projects')
            ->willReturn($show_api);
    }

    /** @return list<string> */
    protected function getShowArguments(string $user, string $repo) : array
    {
        return ["$user/$repo"];
    }

    protected function configureBranchesClient(
        MockObject $client,
        MockObject $branches_api,
        string $user,
        string $repo
    ) : void {
        $client->expects($this->once())
            ->method('repositories')
            ->willReturn($branches_api);
    }

    /** @return list<string> */
    protected function getBranchesArguments(string $user, string $repo) : array
    {
        return ["$user/$repo"];
    }

    protected function configureLastResponse(MockObject $client) : void
    {
        // Gitlab paginates through its own response history, so there is no last
        // response on the client to stub.
    }

    protected function configureAutomergeClient(MockObject $client) : void
    {
        $mock_merge_requests = $this->createMock(MergeRequests::class);
        $mock_merge_requests->method('merge')
            ->willReturn([
                'merge_when_pipeline_succeeds' => true,
            ]);
        $client->method('mergeRequests')
            ->willReturn($mock_merge_requests);
    }

    protected function configurePrsClient(MockObject $client, MockObject $prs_api, string $user, string $repo) : void
    {
        $client->method('mergeRequests')
            ->willReturn($prs_api);
        $client->method('repositories')
            ->willReturn($this->createMock(Repositories::class));
    }

    /** @return list<string> */
    protected function getPrsArguments(string $user, string $repo) : array
    {
        return ["$user/$repo"];
    }
}
