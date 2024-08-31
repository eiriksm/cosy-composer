<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\Providers\Bitbucket;

class BitbucketIntegrationTest extends ComposerUpdateIntegrationBase
{

    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.1.4';
    protected $composerAssetFiles = 'composer.close.outdated';

    private $foundMessage = false;
    private $commandStringToFind = null;

    public function setUp(): void
    {
        parent::setUp();
        $this->foundMessage = false;
        $this->commandStringToFind = null;
    }

    protected function getMockProvider()
    {
        if (!$this->mockProvider) {
            $this->mockProvider = $this->createMock(Bitbucket::class);
        }
        return $this->mockProvider;
    }

    public function testUpdateOauthToken()
    {
        $token = 'verysecret';
        $this->commandStringToFind = 'https://x-token-auth:verysecret@bitbucket.org/user/repo.git';
        $this->cosy->setAuthentication($token);
        $this->cosy->setUrl('https://bitbucket.org/user/repo');
        $this->runtestExpectedOutput();
        self::assertEquals(true, $this->foundMessage);
    }

    public function testUpdateAppPass()
    {
        $token = 'user:verysecret';
        $this->commandStringToFind = 'https://user:verysecret@bitbucket.org/user/repo.git';
        $this->cosy->setAuthentication($token);
        $this->cosy->setUrl('https://bitbucket.org/user/repo');
        $has_passed_user_and_token = false;
        $this->mockProvider->expects($this->exactly(2))
            ->method('authenticate')
            ->willReturnCallback(function ($user, $token) use (&$has_passed_user_and_token) {
                if ($user === 'user' && $token === 'verysecret') {
                    $has_passed_user_and_token = true;
                }
            });
        $this->runtestExpectedOutput();
        self::assertEquals(true, $this->foundMessage);
        self::assertEquals(true, $has_passed_user_and_token);
    }

    protected function handleExecutorReturnCallback($cmd, &$return)
    {
        if (!$this->commandStringToFind) {
            return;
        }
        $command_string = implode(' ', $cmd);
        if (strpos($command_string, $this->commandStringToFind) !== false) {
            $this->foundMessage = true;
        }
    }
}
