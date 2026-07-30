<?php

namespace eiriksm\CosyComposerTest\unit\Providers;

use Bitbucket\Api\Repositories;
use Bitbucket\Api\Repositories\Workspaces;
use Bitbucket\Client;
use eiriksm\CosyComposer\Helpers;
use eiriksm\CosyComposer\ProviderInterface;
use eiriksm\CosyComposer\Providers\Bitbucket;
use PHPUnit\Framework\MockObject\MockObject;
use Violinist\Slug\Slug;

class BitbucketProviderTest extends ProvidersTestBase
{
    /**
     * The commit the pull requests in the fixture point at.
     */
    const COMMIT_HASH = 'cafebabe';

    /**
     * The package the commit message of said commit says it updates.
     */
    const KNOWN_PACKAGE = 'drupal/foo';

    /** @var list<string|null> */
    protected $authenticateArguments = [
        Client::AUTH_HTTP_PASSWORD, 'testUser', 'testPassword',
    ];

    /** @var list<string|null> */
    protected $authenticatePrivateArguments = [
        Client::AUTH_OAUTH_TOKEN, 'testUser',
    ];

    /** @param array<mixed> $branches */
    private function createBitbucketWithBranches(array $branches): Bitbucket
    {
        $mock = $this->getMockBuilder(Bitbucket::class)
            ->setConstructorArgs([$this->createMock(Client::class)])
            ->onlyMethods(['getBranches'])
            ->getMock();
        $mock->method('getBranches')
            ->willReturn($branches);
        return $mock;
    }

    /**
     * Without a token there is nothing to use as a password, so we fall back to oauth.
     */
    public function testAuthenticateWithoutTokenUsesOauth() : void
    {
        $mock_client = $this->getMockClient();
        $mock_client->expects($this->once())
            ->method('authenticate')
            ->with(Client::AUTH_OAUTH_TOKEN, 'testUser');
        $provider = $this->getProvider($mock_client);
        $provider->authenticate('testUser', null);
    }

    public function testDefaultBaseTimestamp(): void
    {
        $slug = Slug::createFromUrl('http://bitbucket.org/testUser/testRepo');
        $provider = $this->createBitbucketWithBranches([
            [
                'name' => 'main',
                'target' => [
                    'hash' => 'abcd1234',
                    'date' => '2025-01-15T10:30:00+00:00',
                ],
            ],
        ]);
        $this->assertEquals('2025-01-15T10:30:00+00:00', $provider->getDefaultBaseTimestamp($slug, 'main'));
    }

    public function testDefaultBaseTimestampReturnsNullForMissingBranch(): void
    {
        $slug = Slug::createFromUrl('http://bitbucket.org/testUser/testRepo');
        $provider = $this->createBitbucketWithBranches([
            [
                'name' => 'other',
                'target' => [
                    'hash' => 'abcd1234',
                    'date' => '2025-01-15T10:30:00+00:00',
                ],
            ],
        ]);
        $this->assertNull($provider->getDefaultBaseTimestamp($slug, 'main'));
    }

    public function testDefaultBaseTimestampReturnsNullForMissingDate(): void
    {
        $slug = Slug::createFromUrl('http://bitbucket.org/testUser/testRepo');
        $provider = $this->createBitbucketWithBranches([
            [
                'name' => 'main',
                'target' => [
                    'hash' => 'abcd1234',
                ],
            ],
        ]);
        $this->assertNull($provider->getDefaultBaseTimestamp($slug, 'main'));
    }

    /**
     * @dataProvider apiTokenProvider
     */
    public function testTokenIndicatesUserApiToken(string $token, bool $expected): void
    {
        $this->assertSame($expected, Bitbucket::tokenIndicatesUserApiToken($token));
    }

    /**
     * @dataProvider apiTokenProvider
     */
    public function testGetApiTokenStripsAnyEmailPrefix(string $token, bool $isApiToken, string $expectedToken): void
    {
        $this->assertSame($expectedToken, Bitbucket::getApiToken($token));
    }

    /**
     * @return array<string, array{0: string, 1: bool, 2: string}>
     */
    public function apiTokenProvider(): array
    {
        // An API token starts with ATAT and is more than 100 chars.
        $api_token = 'ATAT' . str_repeat('x', 100);
        return [
            'bare api token' => [
                $api_token,
                true,
                $api_token,
            ],
            'api token with email prefix' => [
                'me@example.com:' . $api_token,
                true,
                $api_token,
            ],
            'app password (user:pass) is not an api token' => [
                'someuser:somepassword',
                false,
                'somepassword',
            ],
            'short ATAT token is not an api token' => [
                'ATATshort',
                false,
                'ATATshort',
            ],
            'long token not starting with ATAT is not an api token' => [
                str_repeat('x', 120),
                false,
                str_repeat('x', 120),
            ],
        ];
    }

    public function getProvider(object $client) : ProviderInterface
    {
        assert($client instanceof Client);
        return new Bitbucket($client);
    }

    public function getMockClient()
    {
        return $this->createMock(Client::class);
    }

    /** @param string $context */
    protected function getRepoClassName($context) : string
    {
        if ($context === 'branches') {
            return Workspaces\Refs\Branches::class;
        }
        return Workspaces::class;
    }

    protected function getBranchesMethod() : string
    {
        return 'list';
    }

    protected function getPrClassName() : string
    {
        return Workspaces\PullRequests::class;
    }

    protected function getPrListMethod() : string
    {
        return 'list';
    }

    protected function configureShowClient(MockObject $client, MockObject $show_api, string $user, string $repo) : void
    {
        $mock_repositories = $this->createMock(Repositories::class);
        $mock_repositories->expects($this->once())
            ->method('workspaces')
            ->with($user)
            ->willReturn($show_api);
        $client->expects($this->once())
            ->method('repositories')
            ->willReturn($mock_repositories);
    }

    /** @return list<string> */
    protected function getShowArguments(string $user, string $repo) : array
    {
        return [$repo];
    }

    /** @return array<string, mixed> */
    protected function getShowResponse() : array
    {
        return [
            'mainbranch' => [
                'name' => 'master',
            ],
        ];
    }

    protected function configureBranchesClient(
        MockObject $client,
        MockObject $branches_api,
        string $user,
        string $repo
    ) : void {
        $mock_refs = $this->createMock(Workspaces\Refs::class);
        $mock_refs->expects($this->once())
            ->method('branches')
            ->willReturn($branches_api);
        $mock_workspaces = $this->createMock(Workspaces::class);
        $mock_workspaces->expects($this->once())
            ->method('refs')
            ->with($repo)
            ->willReturn($mock_refs);
        $mock_repositories = $this->createMock(Repositories::class);
        $mock_repositories->expects($this->once())
            ->method('workspaces')
            ->with($user)
            ->willReturn($mock_workspaces);
        $client->expects($this->once())
            ->method('repositories')
            ->willReturn($mock_repositories);
    }

    /** @return list<string> */
    protected function getBranchesArguments(string $user, string $repo) : array
    {
        // The paginator calls list() without any arguments.
        return [];
    }

    /**
     * @param list<array<string, mixed>> $branches
     *
     * @return array<mixed>
     */
    protected function getBranchesResponse(array $branches) : array
    {
        return [
            'values' => $branches,
        ];
    }

    protected function configureAutomergeClient(MockObject $client) : void
    {
        // Automerge is not implemented for Bitbucket, so no api calls are made.
    }

    protected function getExpectedAutomergeResult() : bool
    {
        return false;
    }

    protected function configurePrsClient(MockObject $client, MockObject $prs_api, string $user, string $repo) : void
    {
        $mock_commit = $this->createMock(Workspaces\Commit::class);
        $mock_commit->expects($this->exactly(count($this->getPrsFixture())))
            ->method('show')
            ->with(self::COMMIT_HASH)
            ->willReturn([
                'message' => $this->getCommitMessage(),
            ]);
        $mock_workspaces = $this->createMock(Workspaces::class);
        $mock_workspaces->expects($this->once())
            ->method('pullRequests')
            ->with($repo)
            ->willReturn($prs_api);
        $mock_workspaces->method('commit')
            ->with($repo)
            ->willReturn($mock_commit);
        $mock_repositories = $this->createMock(Repositories::class);
        $mock_repositories->method('workspaces')
            ->with($user)
            ->willReturn($mock_workspaces);
        $client->method('repositories')
            ->willReturn($mock_repositories);
    }

    /** @return list<string> */
    protected function getPrsArguments(string $user, string $repo) : array
    {
        // The paginator calls list() without any arguments.
        return [];
    }

    /**
     * @param list<array<string, mixed>> $prs
     *
     * @return array<mixed>
     */
    protected function getPrsResponse(array $prs) : array
    {
        $values = array_map(function (array $pr) : array {
            $pr['state'] = Bitbucket::MERGE_REQUEST_STATE_OPEN;
            $pr['id'] = $pr['iid'];
            $pr['destination'] = [
                'commit' => [
                    'hash' => 'abab',
                ],
                'branch' => [
                    'name' => 'master',
                ],
            ];
            $pr['links'] = [
                'html' => [
                    'href' => 'http://bitbucket.org/testUser/testRepo',
                ],
            ];
            $pr['source'] = [
                'branch' => [
                    'name' => $pr['head']['ref'],
                ],
                'commit' => [
                    'hash' => self::COMMIT_HASH,
                ],
            ];
            return $pr;
        }, $prs);
        return [
            'values' => $values,
        ];
    }

    /** @return list<string> */
    protected function getExpectedKnownPackages() : array
    {
        return [self::KNOWN_PACKAGE];
    }

    /**
     * A commit message in the format violinist writes them, so the package can be parsed out.
     */
    private function getCommitMessage() : string
    {
        return sprintf(
            "Update %s\n%s\nupdate_data:\n  package: %s\n",
            self::KNOWN_PACKAGE,
            Helpers::getCommitMessageSeparator(),
            self::KNOWN_PACKAGE
        );
    }
}
