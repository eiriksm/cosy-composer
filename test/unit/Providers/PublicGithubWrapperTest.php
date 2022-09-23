<?php

namespace eiriksm\CosyComposerTest\unit\Providers;

use eiriksm\CosyComposer\Providers\Github;
use eiriksm\CosyComposer\Providers\PublicGithubWrapper;
use Github\Api\PullRequest;
use Github\Api\Repo;
use Github\Api\Repository\Forks;
use Github\Client;
use Github\Exception\ValidationFailedException;
use GuzzleHttp\Psr7\Utils;
use Http\Client\HttpClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Violinist\ProjectData\ProjectData;
use Violinist\Slug\Slug;

class PublicGithubWrapperTest extends TestCase
{
    /**
     * The provider we are testing.
     *
     * @var PublicGithubWrapper
     */
    protected $provider;

    /**
     * The nid we are using to test.
     *
     * @var int
     */
    protected int $nid;

    /**
     * @var HttpClient
     */
    protected HttpClient $mockHttpClient;

    /**
     * Slug to use.
     *
     * @var Slug
     */
    protected Slug $slug;

    /**
     * A temp dir.
     *
     * @var string
     */
    protected string $tempDir;

    public function setUp(): void
    {
        $mock_client = $this->createMock(Client::class);
        $this->mockClient = $mock_client;
        $this->provider = new PublicGithubWrapper($mock_client);
        $project = new ProjectData();
        $this->nid = random_int(100, 999);
        $project->setNid($this->nid);
        $this->provider->setProject($project);
        $this->mockHttpClient = $this->createMock(HttpClient::class);
        $this->provider->setHttpClient($this->mockHttpClient);
        $this->slug = Slug::createFromUrl('https://github.com/user/project');
        // Place a couple files in here, so we can test their contents are being used I guess.
        $this->tempDir = sprintf('%s/%s', sys_get_temp_dir(), uniqid('my_prefix', true));
        mkdir($this->tempDir);
        foreach (['json', 'lock'] as $suffix) {
            file_put_contents(sprintf('%s/composer.%s', $this->tempDir, $suffix), 'some data');
        }
        // Set these things in there as well.
        $this->provider->setUserToken('my_user_token');
        $this->provider->setUrlFromTokenUrl('https://example.com/token_api');
    }

    public function tearDown(): void
    {
        parent::tearDown();
        foreach (['json', 'lock'] as $suffix) {
            $filename = sprintf('%s/composer.%s', $this->tempDir, $suffix);
            if (!file_exists($filename)) {
                continue;
            }
            unlink($filename);
        }
        rmdir($this->tempDir);
    }

    /**
     * @dataProvider get403Response
     */
    public function testForceUpdateBranchWrongStatusCode($mock_response)
    {
        $this->expectException(\Exception::class);
        $this->mockHttpClient->method('sendRequest')
            ->willReturn($mock_response);
        $this->expectExceptionMessage('Wrong status code on update branch request (403)');
        $this->provider->forceUpdateBranch('my_branch', '123');
    }

    /**
     * @dataProvider getBadJsonResponse
     */
    public function testForceUpdateBranchBadResponseData($mock_response)
    {
        $this->expectException(\Exception::class);
        $this->mockHttpClient->method('sendRequest')
            ->willReturn($mock_response);
        $this->expectExceptionMessage('No json parsed in the update branch response');
        $this->provider->forceUpdateBranch('my_branch', '123');
    }

    /**
     * @dataProvider getOkJsonResponse
     */
    public function testForceUpdateBranchGoodStuff($mock_response)
    {
        $this->mockHttpClient->method('sendRequest')
            ->willReturn($mock_response);
        $this->provider->forceUpdateBranch('my_branch', '123');
        // And make sure we assert something, otherwise the test looks risky.
        self::assertTrue(true);
    }

    /**
     * @dataProvider get403Response
     */
    public function testCreateForkWrongStatusCode($mock_response)
    {
        $this->mockHttpClient->method('sendRequest')
            ->willReturn($mock_response);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Wrong status code on create fork request (403)');
        $this->provider->createFork('user', 'project', 'fork_user');
    }

    /**
     * @dataProvider getBadJsonResponse
     */
    public function testCreateForkBadResponseData($mock_response)
    {
        $this->expectException(\Exception::class);
        $this->mockHttpClient->method('sendRequest')
            ->willReturn($mock_response);
        $this->expectExceptionMessage('No json parsed in the create fork response');
        $this->provider->createFork('user', 'project', 'fork_user');
    }

    /**
     * @dataProvider getOkJsonResponse
     */
    public function testCreateForkGoodStuff($mock_response)
    {
        $this->mockHttpClient->method('sendRequest')
            ->willReturn($mock_response);
        $this->provider->createFork('user', 'project', 'fork_user');
        // And make sure we assert something, otherwise the test looks risky.
        self::assertTrue(true);
    }

    /**
     * @dataProvider get403Response
     */
    public function testCreatePrWrongStatusCode($mock_response)
    {
        $this->mockHttpClient->method('sendRequest')
            ->willReturn($mock_response);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Wrong status code on create PR request (403)');
        $this->provider->createPullRequest($this->slug, []);
    }

    /**
     * Extra case for the HTTP 422 case.
     */
    public function testCreatePrStatusCode422()
    {
        $mock_response = $this->getMockResponseWithStatusCode(422);
        $this->mockHttpClient->method('sendRequest')
            ->willReturn($mock_response);
        $error = 'Remote error coming directly from the API';
        $this->setResponseString($mock_response, json_encode([
            'error' => $error,
        ]));
        $this->expectException(ValidationFailedException::class);
        $this->expectExceptionMessage($error);
        $this->provider->createPullRequest($this->slug, []);
    }

    /**
     * @dataProvider getBadJsonResponse
     */
    public function testCreatePrBadResponseData($mock_response)
    {
        $this->expectException(\Exception::class);
        $this->mockHttpClient->method('sendRequest')
            ->willReturn($mock_response);
        $this->expectExceptionMessage('No json parsed in the create PR response');
        $this->provider->createPullRequest($this->slug, []);
    }

    /**
     * @dataProvider getOkJsonResponse
     */
    public function testCreatePrGoodStuff($mock_response)
    {
        $this->mockHttpClient->method('sendRequest')
            ->willReturn($mock_response);
        $this->provider->createPullRequest($this->slug, []);
        // And make sure we assert something, otherwise the test looks risky.
        self::assertTrue(true);
    }

    /**
     * @dataProvider get403Response
     */
    public function testUpdatePullRequestWrongStatusCode($mock_response)
    {
        $this->mockHttpClient->method('sendRequest')
            ->willReturn($mock_response);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Wrong status code on update PR request (403)');
        $this->provider->updatePullRequest($this->slug, 99, []);
    }

    /**
     * @dataProvider getBadJsonResponse
     */
    public function testUpdatePullRequestBadResponseData($mock_response)
    {
        $this->expectException(\Exception::class);
        $this->mockHttpClient->method('sendRequest')
            ->willReturn($mock_response);
        $this->expectExceptionMessage('No json parsed in the update PR response');
        $this->provider->updatePullRequest($this->slug, 'abc', []);
    }

    /**
     * @dataProvider getOkJsonResponse
     */
    public function testUpdatePullRequestGoodStuff($mock_response)
    {
        $this->mockHttpClient->method('sendRequest')
            ->willReturn($mock_response);
        $this->provider->updatePullRequest($this->slug, 123, []);
        // And make sure we assert something, otherwise the test looks risky.
        self::assertTrue(true);
    }

    /**
     * @dataProvider get403Response
     */
    public function testCommitFilesWrongStatusCode($mock_response)
    {
        $this->mockHttpClient->method('sendRequest')
            ->willReturn($mock_response);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Wrong status code on commit files request (403)');
        $this->provider->commitNewFiles($this->tempDir, 'abc', 'mybranch', 'msg', 'abc');
    }

    /**
     * @dataProvider getBadJsonResponse
     */
    public function testCommitFilesBadResponseData($mock_response)
    {
        $this->expectException(\Exception::class);
        $this->mockHttpClient->method('sendRequest')
            ->willReturn($mock_response);
        $this->expectExceptionMessage('No json parsed in the commit files response');
        $this->provider->commitNewFiles($this->tempDir, 'ab', 'testbranch', 'msg', null);
    }

    /**
     * @dataProvider getOkJsonResponse
     */
    public function testCommitFilesGoodStuff($mock_response)
    {
        $this->mockHttpClient->method('sendRequest')
            ->willReturn($mock_response);
        $this->provider->commitNewFiles($this->tempDir, 'abab', 'mybranchyeah', 'msgz', 'content content content');
        // And make sure we assert something, otherwise the test looks risky.
        self::assertTrue(true);
    }

    public function get403Response()
    {
        return [
            [$this->getMockResponseWithStatusCode(403)],
        ];
    }

    public function getBadJsonResponse()
    {
        return [
            [$this->getMockResponseWithString('total bad json totally yes total. Maybe even <xml></xml> you know. Oh no bad xml as well')],
        ];
    }

    public function getOkJsonResponse()
    {
        return [
            [$this->getMockResponseWithString(json_encode(['json' => 1, 'xml' => false]))],
        ];
    }

    protected function getMockResponseWithStatusCode($code)
    {
        $mock_response = $this->createMock(ResponseInterface::class);
        $mock_response->method('getStatusCode')
            ->willReturn($code);
        return $mock_response;
    }

    protected function getMockResponseWithString($response_string)
    {
        $mock_response = $this->getMockResponseWithStatusCode(200);
        $this->setResponseString($mock_response, $response_string);
        return $mock_response;
    }

    protected function setResponseString($mock_response, $response_string)
    {
        $stream = Utils::streamFor($response_string);
        $mock_response->method('getBody')
            ->willReturn($stream);
    }
}
