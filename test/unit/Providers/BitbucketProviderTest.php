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
}
