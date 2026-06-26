<?php

namespace eiriksm\CosyComposerTest\unit\Providers;

use Bitbucket\Client;
use eiriksm\CosyComposer\Providers\Bitbucket;
use PHPUnit\Framework\TestCase;
use Violinist\Slug\Slug;

class BitbucketProviderTest extends TestCase
{
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
}
