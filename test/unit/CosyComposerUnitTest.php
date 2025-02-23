<?php

namespace eiriksm\CosyComposerTest\unit;

use eiriksm\CosyComposer\CommandExecuter;
use eiriksm\CosyComposer\CosyComposer;
use eiriksm\CosyComposer\Helpers;
use eiriksm\CosyComposerTest\GetCosyTrait;
use eiriksm\CosyComposerTest\GetExecuterTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CosyComposerUnitTest extends TestCase
{
    use GetCosyTrait;
    use GetExecuterTrait;

    public function tearDown(): void
    {
        parent::tearDown();
        // Make sure we clear out the env var USE_GITHUB_PUBLIC_WRAPPER.
        putenv('USE_GITHUB_PUBLIC_WRAPPER');
    }

    public function testSetLogger()
    {
        $c = $this->getMockCosy();
        $test_logger = $this->createMock(LoggerInterface::class);
        $c->setLogger($test_logger);
        $this->assertEquals($test_logger, $c->getLogger());
    }

    public function testCacheDir()
    {
        $c = $this->getMockCosy();
        $bogus_dir = uniqid();
        // This has been deprecated so currently this test makes no sense.
        $c->setCacheDir($bogus_dir);
        $this->assertEquals('', $c->getCacheDir());
    }

    public function testLastStdOut()
    {
        $c = $this->getMockCosy();
        $mock_exec = $this->createMock(CommandExecuter::class);
        $mock_exec->expects($this->once())
            ->method('getLastOutput')
            ->willReturn([
                'stdout' => 'output',
            ]);
        $c->setExecuter($mock_exec);
        $this->assertEquals('output', $c->getLastStdOut());
    }

    /**
     * @dataProvider setUrlValues
     */
    public function testSetUrl($url, $user, $repo, $host, $port)
    {
        // Use reflection to invoke the protected method we want to test.
        $class = new \ReflectionClass(CosyComposer::class);
        $property = $class->getProperty('slug');
        $url_property = $class->getProperty('urlArray');
        $property->setAccessible(true);
        $url_property->setAccessible(true);
        $mock_cosy = $this->getMockCosy();
        $mock_cosy->setUrl($url);
        /** @var \Violinist\Slug\Slug $value */
        $value = $property->getValue($mock_cosy);
        $this->assertEquals($user, $value->getUserName());
        $this->assertEquals($repo, $value->getUserRepo());
        $url_value = $url_property->getValue($mock_cosy);
        $this->assertEquals($url_value['host'], $host);
        $this->assertEquals($url_value['port'], $port);
    }

    public function setUrlValues()
    {
        return [
            [
                'url' => 'https://github.com/user/repo',
                'user' => 'user',
                'repo' => 'repo',
                'host' => 'github.com',
                'port' => 443,
            ],
            [
                'url' => 'http://example.com/user/repo',
                'user' => 'user',
                'repo' => 'repo',
                'host' => 'example.com',
                'port' => 80,
            ],
            [
                'url' => 'https://internal.gitlab.instance:2278/user/repo',
                'user' => 'user',
                'repo' => 'repo',
                'host' => 'internal.gitlab.instance',
                'port' => 2278,
            ],
        ];
    }

    /**
     * @dataProvider getComposerJsonVariations
     */
    public function testGetComposerJsonName($json, $input, $expected)
    {
        $this->assertEquals($expected, Helpers::getComposerJsonName($json, $input, '/tmp/derp'));
    }

    public function getComposerJsonVariations()
    {
        $standard_json = (object) [
            'require' => (object) [
                'camelCase/other' => '1.0',
                'regular/case' => '1.0',
                'UPPER/CASE' => '1.0',
            ],
            'require-dev' => (object) [
                'camelCaseDev/other' => '1.0',
                'regulardev/case' => '1.0',
                'UPPERDEV/CASE' => '1.0',
            ],
        ];
        return [
            [$standard_json, 'camelcase/other', 'camelCase/other'],
            [$standard_json, 'Regular/Case', 'regular/case'],
            [$standard_json, 'regular/case', 'regular/case'],
            [$standard_json, 'upper/case', 'UPPER/CASE'],
            [$standard_json, 'camelcasedev/other', 'camelCaseDev/other'],
            [$standard_json, 'camelcaseDev/other', 'camelCaseDev/other'],
            [$standard_json, 'regulardev/case', 'regulardev/case'],
            [$standard_json, 'UPPERDEV/case', 'UPPERDEV/CASE'],
        ];
    }

    public function testDeprecatedAuth()
    {
        $c = new CosyComposer($this->createMock(CommandExecuter::class));
        $c->setGithubAuth('token', 'not-relevant');
        $reflected_cosy = new \ReflectionClass($c);
        $prop = $reflected_cosy->getProperty('untouchedUserToken');
        $prop->setAccessible(true);
        self::assertEquals($prop->getValue($c), 'token');
        // Now let's do the same with the other thing.
        $c->setUserToken('another-one-token');
        self::assertEquals($prop->getValue($c), 'another-one-token');
    }

    /**
     * Test that a special flag gives us the correct answer of a method.
     *
     * @dataProvider getEnvVariations
     */
    public function testshouldEnablePublicGithubWrapper($env_var, $expected)
    {
        putenv('USE_GITHUB_PUBLIC_WRAPPER=' . $env_var);
        $this->assertEquals($expected, CosyComposer::shouldEnablePublicGithubWrapper());
    }

    public function getEnvVariations()
    {
        return [
            ['true', true],
            ['TRUE', true],
            ['1', true],
            ['0', false],
            ['derp', true],
        ];
    }
}
