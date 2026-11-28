<?php

namespace eiriksm\CosyComposerTest\unit\Providers;

use Bitbucket\Api\CurrentUser;
use Bitbucket\Api\Repositories;
use Bitbucket\Api\Repositories\Workspaces;
use Bitbucket\Client;
use Bitbucket\Exception\ValidationFailedException;
use Wa72\SimpleLogger\ArrayLogger;
use eiriksm\CosyComposer\ProviderInterface;
use eiriksm\CosyComposer\Providers\Bitbucket;
use eiriksm\CosyComposer\Providers\NamedPrs;
use PHPUnit\Framework\MockObject\MockObject;
use Violinist\Slug\Slug;

class BitbucketProviderTest extends ProvidersTestBase
{
    /**
     * The commit the pull requests in the fixture point at.
     */
    const COMMIT_HASH = 'cafebabe';

    /**
     * The destination commit of the pull requests in the fixture.
     */
    const DESTINATION_HASH = 'abab';

    /**
     * What the merge-base api returns, or throws.
     *
     * @var array<string, mixed>|\Throwable
     */
    private $mergeBaseResult = [];

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
     * The base sha of an open PR is the merge base, not the destination tip.
     *
     * @see https://github.com/eiriksm/cosy-composer/issues/523
     */
    public function testPrsNamedUsesMergeBaseAsBaseSha() : void
    {
        $merge_base = 'b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0b0';
        $this->mergeBaseResult = ['hash' => $merge_base];
        $prs = $this->getPrsNamedFromFixture();
        foreach ($prs->getAllPrsNamed() as $pr) {
            $this->assertSame(substr($merge_base, 0, 12), $pr['base']['sha']);
        }
    }

    /**
     * If the merge base can not be found, use the destination commit.
     */
    public function testPrsNamedFallsBackToDestinationWhenMergeBaseFails() : void
    {
        $this->mergeBaseResult = new \RuntimeException('Not found', 404);
        $prs = $this->getPrsNamedFromFixture();
        foreach ($prs->getAllPrsNamed() as $pr) {
            $this->assertSame(self::DESTINATION_HASH, $pr['base']['sha']);
        }
    }

    private function getPrsNamedFromFixture() : NamedPrs
    {
        $user = 'testUser';
        $repo = 'testRepo';
        $mock_prs_api = $this->createMock(Workspaces\PullRequests::class);
        $mock_prs_api->expects($this->once())
            ->method('list')
            ->willReturn($this->getPrsResponse($this->getPrsFixture()));
        $mock_client = $this->getMockClient();
        $this->configurePrsClient($mock_client, $mock_prs_api, $user, $repo);
        $this->configureLastResponse($mock_client);
        $provider = $this->getProvider($mock_client);
        $prs = $provider->getPrsNamed(Slug::createFromUrl('http://bitbucket.org/testUser/testRepo'));
        $this->assertEquals(['patch-1', 'patch-2'], array_keys($prs->getAllPrsNamed()));
        return $prs;
    }

    /**
     * A Bitbucket client whose pull request api is the given mock.
     */
    private function createBitbucketWithPrsApi(MockObject $prs_api) : Bitbucket
    {
        $mock_workspaces = $this->createMock(Workspaces::class);
        $mock_workspaces->method('pullRequests')
            ->with('testRepo')
            ->willReturn($prs_api);
        $mock_repositories = $this->createMock(Repositories::class);
        $mock_repositories->method('workspaces')
            ->with('testUser')
            ->willReturn($mock_workspaces);
        $mock_client = $this->createMock(Client::class);
        $mock_client->method('repositories')
            ->willReturn($mock_repositories);
        return new Bitbucket($mock_client);
    }

    private function getSlug() : Slug
    {
        return Slug::createFromUrl('https://bitbucket.org/testUser/testRepo');
    }

    /**
     * The pull request is declined before the comment is posted.
     */
    public function testClosePullRequestWithCommentDeclinesFirst() : void
    {
        $calls = [];
        $comments_api = $this->createMock(Workspaces\PullRequests\Comments::class);
        $comments_api->expects($this->once())
            ->method('create')
            ->with([
                'content' => [
                    'raw' => 'comment',
                ],
            ])
            ->willReturnCallback(function () use (&$calls) {
                $calls[] = 'comment';
                return [];
            });
        $prs_api = $this->createMock(Workspaces\PullRequests::class);
        $prs_api->expects($this->once())
            ->method('decline')
            ->with('42')
            ->willReturnCallback(function () use (&$calls) {
                $calls[] = 'decline';
                return [];
            });
        $prs_api->method('comments')
            ->with('42')
            ->willReturn($comments_api);
        $provider = $this->createBitbucketWithPrsApi($prs_api);
        $provider->closePullRequestWithComment($this->getSlug(), 42, 'comment');
        $this->assertEquals(['decline', 'comment'], $calls);
    }

    /**
     * A pull request that was merged or declined in the meantime gets no comment.
     *
     * Bitbucket refuses to decline a pull request that is not open, with this
     * exact error for both merged and declined pull requests.
     */
    public function testClosePullRequestWithCommentDoesNotCommentOnMergedPullRequest() : void
    {
        $prs_api = $this->createMock(Workspaces\PullRequests::class);
        $prs_api->method('decline')
            ->willThrowException(new ValidationFailedException('newstatus: This pull request is already closed.', 400));
        $prs_api->expects($this->never())
            ->method('comments');
        $provider = $this->createBitbucketWithPrsApi($prs_api);
        $this->expectException(ValidationFailedException::class);
        $provider->closePullRequestWithComment($this->getSlug(), 42, 'comment');
    }

    /**
     * @return array<string, mixed>
     */
    private function getPrParams() : array
    {
        return [
            'base' => 'main',
            'head' => 'psrlog100200',
            'title' => 'Update psr/log from 1.0.0 to 2.0.0',
            'body' => 'New body',
            'assignees' => ['configured-user'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getExistingPr() : array
    {
        return [
            'id' => 42,
            'title' => 'Old title',
            'reviewers' => [
                [
                    'uuid' => '{11111111-1111-1111-1111-111111111111}',
                    'account_id' => '5b10a2844c20165700ede21g',
                    'nickname' => 'added-by-hand',
                    'display_name' => 'Added By Hand',
                ],
            ],
            'links' => [
                'html' => [
                    'href' => 'https://bitbucket.org/testUser/testRepo/pull-requests/42',
                ],
            ],
        ];
    }

    /**
     * Updating a pull request must not remove the reviewers someone added.
     */
    public function testUpdatePullRequestKeepsExistingReviewers() : void
    {
        $prs_api = $this->createMock(Workspaces\PullRequests::class);
        $prs_api->expects($this->once())
            ->method('show')
            ->with('42')
            ->willReturn($this->getExistingPr());
        $prs_api->expects($this->once())
            ->method('update')
            ->with('42', [
                'title' => 'Update psr/log from 1.0.0 to 2.0.0',
                'reviewers' => [
                    ['uuid' => '{11111111-1111-1111-1111-111111111111}'],
                    ['username' => 'configured-user'],
                ],
                'description' => 'New body',
                'destination' => [
                    'branch' => [
                        'name' => 'main',
                    ],
                ],
            ])
            ->willReturn($this->getExistingPr());
        $provider = $this->createBitbucketWithPrsApi($prs_api);
        $provider->updatePullRequest($this->getSlug(), 42, $this->getPrParams());
    }

    /**
     * A reviewer that can no longer be added should not block the update.
     */
    public function testUpdatePullRequestDropsExistingReviewersBitbucketRejects() : void
    {
        $prs_api = $this->createMock(Workspaces\PullRequests::class);
        $prs_api->method('show')
            ->willReturn($this->getExistingPr());
        $calls = [];
        $prs_api->expects($this->exactly(3))
            ->method('update')
            ->willReturnCallback(function (string $id, array $params) use (&$calls) {
                $calls[] = $params['reviewers'];
                if (in_array(['uuid' => '{11111111-1111-1111-1111-111111111111}'], $params['reviewers'], true)) {
                    throw new ValidationFailedException('Malformed reviewers list', 400);
                }
                return $this->getExistingPr();
            });
        $provider = $this->createBitbucketWithPrsApi($prs_api);
        $provider->updatePullRequest($this->getSlug(), 42, $this->getPrParams());
        $this->assertEquals([
            [
                ['uuid' => '{11111111-1111-1111-1111-111111111111}'],
                ['username' => 'configured-user'],
            ],
            [
                ['uuid' => '{11111111-1111-1111-1111-111111111111}'],
            ],
            [
                ['username' => 'configured-user'],
            ],
        ], $calls);
    }

    /**
     * A configured assignee Bitbucket rejects should not block the update.
     *
     * For example a deactivated account, or the account that authored the
     * pull request. Bitbucket used to ignore the assignees on updates, so a
     * bad one in the config never stopped a pull request from being updated.
     *
     * @dataProvider getRejectedAssigneeScenarios
     *
     * @param array<mixed> $existing_reviewers
     * @param array<mixed> $expected_reviewers
     * @param list<string> $expected_messages
     */
    public function testUpdatePullRequestWithAssigneeBitbucketRejects(array $existing_reviewers, array $expected_reviewers, array $expected_messages) : void
    {
        $existing_pr = $this->getExistingPr();
        $existing_pr['reviewers'] = $existing_reviewers;
        $prs_api = $this->createMock(Workspaces\PullRequests::class);
        $prs_api->method('show')
            ->willReturn($existing_pr);
        $successful_reviewers = null;
        $prs_api->method('update')
            ->willReturnCallback(function (string $id, array $params) use (&$successful_reviewers, $existing_pr) {
                if (in_array(['username' => 'configured-user'], $params['reviewers'], true)) {
                    throw new ValidationFailedException('configured-user is not a valid reviewer', 400);
                }
                $successful_reviewers = $params['reviewers'];
                return $existing_pr;
            });
        $provider = $this->createBitbucketWithPrsApi($prs_api);
        $logger = new ArrayLogger();
        $provider->setLogger($logger);
        $provider->updatePullRequest($this->getSlug(), 42, $this->getPrParams());
        $this->assertEquals($expected_reviewers, $successful_reviewers);
        $messages = array_map(function (array $entry) {
            return (string) $entry['message'];
        }, $logger->get());
        $this->assertEquals($expected_messages, $messages);
    }

    /**
     * When no set of reviewers works, the error from Bitbucket is thrown.
     */
    public function testUpdatePullRequestThrowsWhenEveryAttemptFails() : void
    {
        $prs_api = $this->createMock(Workspaces\PullRequests::class);
        $prs_api->method('show')
            ->willReturn($this->getExistingPr());
        $prs_api->expects($this->exactly(4))
            ->method('update')
            ->willThrowException(new ValidationFailedException('Invalid title', 400));
        $provider = $this->createBitbucketWithPrsApi($prs_api);
        $logger = new ArrayLogger();
        $provider->setLogger($logger);
        try {
            $provider->updatePullRequest($this->getSlug(), 42, $this->getPrParams());
            $this->fail('Expected the update to fail');
        } catch (ValidationFailedException $e) {
            $this->assertEquals('Invalid title', $e->getMessage());
        }
        $last = $logger->get()[count($logger->get()) - 1];
        $this->assertEquals('error', $last['level']);
        $this->assertEquals(
            'Could not update Bitbucket pull request 42 with any set of reviewers. The last error was: Invalid title',
            (string) $last['message']
        );
    }

    /**
     * @return array<string, array<mixed>>
     */
    public static function getRejectedAssigneeScenarios() : array
    {
        return [
            'keeps the existing reviewers' => [
                [
                    [
                        'uuid' => '{11111111-1111-1111-1111-111111111111}',
                        'nickname' => 'added-by-hand',
                    ],
                ],
                [
                    ['uuid' => '{11111111-1111-1111-1111-111111111111}'],
                ],
                [
                    'Updating Bitbucket pull request 42 with 1 existing reviewer(s) and 1 configured assignee(s) as reviewer(s)',
                    'Bitbucket rejected the update of pull request 42 (configured-user is not a valid reviewer). Retrying with 1 existing reviewer(s)',
                    'Updated Bitbucket pull request 42 with 1 existing reviewer(s)',
                ],
            ],
            'no existing reviewers' => [
                [],
                [],
                [
                    'Updating Bitbucket pull request 42 with 1 configured assignee(s) as reviewer(s)',
                    'Bitbucket rejected the update of pull request 42 (configured-user is not a valid reviewer). Retrying with no reviewers',
                    'Updated Bitbucket pull request 42 with no reviewers',
                ],
            ],
        ];
    }

    /**
     * Pushing to the branch of an open pull request updates that pull request.
     *
     * Bitbucket would otherwise update it through the create endpoint, and
     * replace its reviewers.
     */
    public function testCreatePullRequestUpdatesExistingPullRequest() : void
    {
        $prs_api = $this->createMock(Workspaces\PullRequests::class);
        $prs_api->expects($this->once())
            ->method('list')
            ->with([
                'q' => 'source.branch.name = "psrlog100200" AND destination.branch.name = "main" AND state = "OPEN"',
            ])
            ->willReturn([
                'values' => [
                    ['id' => 42],
                ],
            ]);
        $prs_api->expects($this->never())
            ->method('create');
        $prs_api->method('show')
            ->willReturn($this->getExistingPr());
        $prs_api->expects($this->once())
            ->method('update')
            ->with('42', $this->callback(function (array $params) {
                return $params['reviewers'] === [
                    ['uuid' => '{11111111-1111-1111-1111-111111111111}'],
                    ['username' => 'configured-user'],
                ];
            }))
            ->willReturn($this->getExistingPr());
        $provider = $this->createBitbucketWithPrsApi($prs_api);
        $data = $provider->createPullRequest($this->getSlug(), $this->getPrParams());
        $this->assertEquals(42, $data['number']);
        $this->assertEquals('https://bitbucket.org/testUser/testRepo/pull-requests/42', $data['html_url']);
    }

    public function testCreatePullRequestCreatesNewPullRequest() : void
    {
        $prs_api = $this->createMock(Workspaces\PullRequests::class);
        $prs_api->method('list')
            ->willReturn(['values' => []]);
        $prs_api->expects($this->never())
            ->method('update');
        $prs_api->expects($this->once())
            ->method('create')
            ->with([
                'title' => 'Update psr/log from 1.0.0 to 2.0.0',
                'source' => [
                    'branch' => [
                        'name' => 'psrlog100200',
                    ],
                ],
                'destination' => [
                    'branch' => [
                        'name' => 'main',
                    ],
                ],
                'description' => 'New body',
                'reviewers' => [
                    ['username' => 'configured-user'],
                ],
            ])
            ->willReturn($this->getExistingPr());
        $provider = $this->createBitbucketWithPrsApi($prs_api);
        $data = $provider->createPullRequest($this->getSlug(), $this->getPrParams());
        $this->assertEquals(42, $data['number']);
    }

    /**
     * A configured assignee that is already a reviewer is not added twice.
     */
    public function testCreateReviewersSkipsDuplicates() : void
    {
        $existing = $this->getExistingPr()['reviewers'];
        $this->assertEquals([
            ['uuid' => '{11111111-1111-1111-1111-111111111111}'],
        ], Bitbucket::createReviewers($existing, ['added-by-hand', '{11111111-1111-1111-1111-111111111111}']));
        $this->assertEquals([], Bitbucket::createReviewers([], []));
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

    public function testGetAuthenticatedUsername(): void
    {
        $mock_current_user_api = $this->createMock(CurrentUser::class);
        $mock_current_user_api->expects($this->once())
            ->method('show')
            ->willReturn([
                'uuid' => '{some-uuid}',
            ]);
        $mock_client = $this->getMockClient();
        $mock_client->expects($this->once())
            ->method('currentUser')
            ->willReturn($mock_current_user_api);
        $g = new Bitbucket($mock_client);
        $this->assertEquals('{some-uuid}', $g->getAuthenticatedUsername());
        // A second call should be served from cache, not hit the api again.
        $this->assertEquals('{some-uuid}', $g->getAuthenticatedUsername());
    }

    public function testGetAuthenticatedUsernameReturnsNullOnException(): void
    {
        $mock_client = $this->getMockClient();
        $mock_client->method('currentUser')
            ->willThrowException(new \RuntimeException('API error'));
        $g = new Bitbucket($mock_client);
        $this->assertNull($g->getAuthenticatedUsername());
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
        // One of the PR fixtures is a closed one, so we expect show to be called same amount of PRs minus that one.
        $number_of_pr_show_calls = count($this->getPrsFixture()) - 1;
        $mock_commit->expects($this->exactly($number_of_pr_show_calls))
            ->method('show')
            ->with(self::COMMIT_HASH)
            ->willReturn([
                'message' => 'Update something',
            ]);
        $mock_merge_bases = $this->createMock(Workspaces\MergeBases::class);
        $merge_base_show = $mock_merge_bases->expects($this->exactly($number_of_pr_show_calls))
            ->method('show')
            ->with(self::COMMIT_HASH . '..' . self::DESTINATION_HASH);
        if ($this->mergeBaseResult instanceof \Throwable) {
            $merge_base_show->willThrowException($this->mergeBaseResult);
        } else {
            $merge_base_show->willReturn($this->mergeBaseResult);
        }
        $mock_workspaces = $this->createMock(Workspaces::class);
        $mock_workspaces->expects($this->once())
            ->method('pullRequests')
            ->with($repo)
            ->willReturn($prs_api);
        $mock_workspaces->method('commit')
            ->with($repo)
            ->willReturn($mock_commit);
        $mock_workspaces->method('mergeBases')
            ->with($repo)
            ->willReturn($mock_merge_bases);
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
                    'hash' => self::DESTINATION_HASH,
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
}
