<?php

namespace eiriksm\CosyComposerTest\unit\Providers;

use Bitbucket\Api\Repositories as BitbucketRepositories;
use Bitbucket\Api\Repositories\Workspaces;
use Bitbucket\Api\Repositories\Workspaces\Refs;
use Bitbucket\Api\Repositories\Workspaces\Refs\Branches;
use Bitbucket\Client as BitbucketClient;
use eiriksm\CosyComposer\Providers\Bitbucket;
use eiriksm\CosyComposer\Providers\Github;
use eiriksm\CosyComposer\Providers\Gitlab;
use Github\Api\GitData;
use Github\Api\GitData\References;
use Github\Client as GithubClient;
use Gitlab\Api\Repositories as GitlabRepositories;
use Gitlab\Client as GitlabClient;
use PHPUnit\Framework\TestCase;
use Violinist\Slug\Slug;

class DeleteBranchProviderTest extends TestCase
{
    public function testGithubDeleteBranch() : void
    {
        $slug = Slug::createFromUrl('https://github.com/testUser/testRepo');
        $references = $this->createMock(References::class);
        $references->expects($this->once())
            ->method('remove')
            ->with('testUser', 'testRepo', 'heads/violinist-test');
        $git = $this->createMock(GitData::class);
        $git->expects($this->once())
            ->method('references')
            ->willReturn($references);
        $client = $this->createMock(GithubClient::class);
        $client->expects($this->once())
            ->method('api')
            ->with('git')
            ->willReturn($git);

        (new Github($client))->deleteBranch($slug, 'violinist-test');
    }

    public function testGitlabDeleteBranch() : void
    {
        $slug = Slug::createFromUrl('https://gitlab.com/testUser/testRepo');
        $repositories = $this->createMock(GitlabRepositories::class);
        $repositories->expects($this->once())
            ->method('deleteBranch')
            ->with('testUser/testRepo', 'violinist-test');
        $client = $this->createMock(GitlabClient::class);
        $client->expects($this->once())
            ->method('repositories')
            ->willReturn($repositories);

        (new Gitlab($client))->deleteBranch($slug, 'violinist-test');
    }

    public function testBitbucketDeleteBranch() : void
    {
        $slug = Slug::createFromUrl('https://bitbucket.org/testUser/testRepo');
        $branches = $this->createMock(Branches::class);
        $branches->expects($this->once())
            ->method('remove')
            ->with('violinist-test')
            ->willReturn([]);
        $refs = $this->createMock(Refs::class);
        $refs->expects($this->once())
            ->method('branches')
            ->willReturn($branches);
        $workspaces = $this->createMock(Workspaces::class);
        $workspaces->expects($this->once())
            ->method('refs')
            ->with('testRepo')
            ->willReturn($refs);
        $repositories = $this->createMock(BitbucketRepositories::class);
        $repositories->expects($this->once())
            ->method('workspaces')
            ->with('testUser')
            ->willReturn($workspaces);
        $client = $this->createMock(BitbucketClient::class);
        $client->expects($this->once())
            ->method('repositories')
            ->willReturn($repositories);

        (new Bitbucket($client))->deleteBranch($slug, 'violinist-test');
    }
}
