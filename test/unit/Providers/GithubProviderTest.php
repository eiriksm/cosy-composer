<?php

namespace eiriksm\CosyComposerTest\unit\Providers;

use eiriksm\CosyComposer\Providers\Github;
use Github\Api\PullRequest;
use Github\Api\Repo;
use Github\Api\Repository\Forks;
use Github\Client;
use Psr\Http\Message\ResponseInterface;
use Violinist\Slug\Slug;

class GithubProviderTest extends ProvidersTestBase
{
    protected $repoClass = Repo::class;

    protected $authenticateArguments = [
        'testUser', null, Client::AUTH_HTTP_TOKEN,
    ];

    protected $authenticatePrivateArguments = [
        'testUser', null, Client::AUTH_HTTP_TOKEN
    ];

    public function testRepoIsPrivate()
    {
        $slug = Slug::createFromUrl('http://github.com/testUser/testRepo');
        $mock_repo_api = $this->createMock(Repo::class);
        $mock_repo_api->expects($this->once())
            ->method('show')
            ->with($slug->getUserName(), $slug->getUserRepo())
            ->willReturn([
                'private' => true,
            ]);
        $mock_client = $this->getMockClient();
        $mock_client->expects($this->once())
            ->method('api')
            ->with('repo')
            ->willReturn($mock_repo_api);
        $g = new Github($mock_client);
        $this->assertEquals(true, $g->repoIsPrivate($slug));
    }

    public function testRepoIsPublic()
    {
        $slug = Slug::createFromUrl('http://github.com/testUser/testRepo');
        $mock_repo_api = $this->createMock(Repo::class);
        $mock_repo_api->expects($this->once())
            ->method('show')
            ->with($slug->getUserName(), $slug->getUserRepo())
            ->willReturn([
                'private' => false,
            ]);
        $mock_client = $this->getMockClient();
        $mock_client->expects($this->once())
            ->method('api')
            ->with('repo')
            ->willReturn($mock_repo_api);
        $g = new Github($mock_client);
        $this->assertEquals(false, $g->repoIsPrivate($slug));
    }

    public function testDefaultBase()
    {
        $slug = Slug::createFromUrl('http://github.com/testUser/testRepo');
        $mock_repo_api = $this->createMock(Repo::class);
        $mock_repo_api->expects($this->once())
            ->method('branches')
            ->with($slug->getUserName(), $slug->getUserRepo())
            ->willReturn([
                [
                    'name' => 'master',
                    'commit' => [
                        'sha' => 'abcd',
                    ],
                ],
                [
                    'name' => 'develop',
                    'commit' => [
                        'sha' => '1234',
                    ]
                ],
            ]);
        $mock_client = $this->getMockClient();
        $mock_client->expects($this->once())
            ->method('api')
            ->with('repo')
            ->willReturn($mock_repo_api);
        $mock_response = $this->createMock(ResponseInterface::class);
        $mock_client->expects($this->once())
            ->method('getLastResponse')
            ->willReturn($mock_response);
        $g = new Github($mock_client);
        $this->assertEquals('abcd', $g->getDefaultBase($slug, 'master'));
    }

    public function testCreateFork()
    {
        $user = 'testUser';
        $repo = 'testRepo';
        $fork_user = 'forkUser';
        $testresponse = 'testresponse';
        $mock_forks = $this->createMock(Forks::class);
        $mock_forks->expects($this->once())
            ->method('create')
            ->with($user, $repo, ['organization' => $fork_user])
            ->willReturn($testresponse);
        $mock_repo_api = $this->createMock(Repo::class);
        $mock_repo_api->expects($this->once())
            ->method('forks')
            ->willReturn($mock_forks);
        $mock_client = $this->getMockClient();
        $mock_client->expects($this->once())
            ->method('api')
            ->with('repo')
            ->willReturn($mock_repo_api);
        $g = new Github($mock_client);
        $this->assertEquals($testresponse, $g->createFork($user, $repo, $fork_user));
    }

    public function testCreatePR()
    {
        list($user, $repo, $params) = $this->getPrData();
        $slug = Slug::createFromUrl('http://github.com/' . $user . '/' . $repo);
        $testresponse = 'testresponse';
        $mock_pr_api = $this->createMock(PullRequest::class);
        $mock_pr_api->expects($this->once())
            ->method('create')
            ->with($user, $repo, $params)
            ->willReturn($testresponse);
        $mock_client = $this->getMockClient();
        $mock_client->expects($this->once())
            ->method('api')
            ->with('pull_request')
            ->willReturn($mock_pr_api);
        $g = new Github($mock_client);
        $this->assertEquals($testresponse, $g->createPullRequest($slug, $params));
    }

    public function testUpdatePR()
    {
        list($user, $repo, $params) = $this->getPrData();
        $id = 42;
        $slug = Slug::createFromUrl('http://github.com/' . $user . '/' . $repo);
        $testresponse = 'testresponse';
        $mock_pr_api = $this->createMock(PullRequest::class);
        $mock_pr_api->expects($this->once())
            ->method('update')
            ->with($user, $repo, $id, $params)
            ->willReturn($testresponse);
        $mock_client = $this->getMockClient();
        $mock_client->expects($this->once())
            ->method('api')
            ->with('pull_request')
            ->willReturn($mock_pr_api);
        $g = new Github($mock_client);
        $this->assertEquals($testresponse, $g->updatePullRequest($slug, $id, $params));
    }

    public function getProvider($client)
    {
        return new Github($client);
    }


    public function getMockClient()
    {
        return $this->createMock(Client::class);
    }

    public function getBranchMethod()
    {
        return 'repo';
    }
}
