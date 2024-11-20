<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\Providers\Bitbucket;

class GitlabIntegrationTest extends ComposerUpdateIntegrationBase
{

    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.1.4';
    protected $composerAssetFiles = 'composer.close.outdated';

    private $foundMessage = false;

    public function setUp(): void
    {
        parent::setUp();
        $this->foundMessage = false;
    }

    protected function getMockProvider()
    {
        if (!$this->mockProvider) {
            $this->mockProvider = $this->createMock(Bitbucket::class);
        }
        return $this->mockProvider;
    }

    public function testUpdateToken()
    {
        $token = 'verysecret';
        $this->cosy->setAuthentication($token);
        $this->cosy->setUrl('https://gitlab.com/user/repo');
        $this->runtestExpectedOutput();
        self::assertEquals(true, $this->foundMessage);
    }

    protected function handleExecutorReturnCallback($cmd, &$return)
    {
        $command_string = implode(' ', $cmd);
        if (strpos($command_string, 'https://oauth2:verysecret@gitlab.com/user/repo') !== false) {
            $this->foundMessage = true;
        }
    }
}
