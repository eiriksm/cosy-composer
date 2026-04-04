<?php

namespace eiriksm\CosyComposerTest\unit\Providers;

use eiriksm\CosyComposer\Providers\Gitlab;
use Gitlab\Api\MergeRequests;
use Gitlab\Api\Projects;
use Gitlab\Api\Repositories;
use Gitlab\Client;
use Violinist\Slug\Slug;

class GitlabProviderTest extends ProvidersTestBase
{
    protected $authenticateArguments = [
        'testUser',
        Client::AUTH_OAUTH_TOKEN,
    ];

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

    public function getProvider(object $client)
    {
        return new Gitlab($client);
    }

    public function getMockClient()
    {
        return $this->createMock(Client::class);
    }

    public function getBranchMethod()
    {
        return 'projects';
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
}
