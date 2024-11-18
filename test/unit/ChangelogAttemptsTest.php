<?php

namespace eiriksm\CosyComposerTest\unit;

use eiriksm\CosyComposerTest\GetCosyTrait;
use eiriksm\CosyComposerTest\GetExecuterTrait;
use PHPUnit\Framework\TestCase;

class ChangelogAttemptsTest extends TestCase
{
    use GetExecuterTrait;
    use GetCosyTrait;

    /**
     * Cosy composer.
     *
     * @var \eiriksm\CosyComposer\CosyComposer
     */
    protected $cosy;

    public function setUp() : void
    {
        parent::setUp();
        $this->cosy = $this->getMockCosy();
    }

    public function testClonesForPublicPackage()
    {
        $called = false;
        $mock_executer = $this->getMockExecuterWithReturnCallback(function ($command_array) use (&$called) {
            $command = implode(' ', $command_array);
            if (strpos($command, 'git clone https://github.com/psr/log') === 0) {
                $called = true;
            }
            return 0;
        });
        $mock_executer->expects($this->once())
            ->method('getLastOutput')
            ->willReturn([
                'stdout' => "112233 This is the first line\n445566 This is the second line",
            ]);
        $this->cosy->setExecuter($mock_executer);
        $log = $this->cosy->retrieveChangeLog('psr/log', json_decode(json_encode(['packages' => [
            [
                'name' => 'psr/log',
                'source' => [
                    'type' => 'git',
                    'url' => 'https://github.com/psr/log',
                ],
            ],
        ]])), 1, 2);
        self::assertEquals(true, $called);
    }

    public function testClonedPrivate()
    {
        $called = false;
        $mock_executer = $this->getMockExecuterWithReturnCallback(function ($command_array) use (&$called) {
            $return = 1;
            $command = implode(' ', $command_array);
            if (strpos($command, 'git clone https://x-access-token:user-token@github.com/user/private') === 0) {
                $called = true;
                $return = 0;
            }
            if (strpos($command, 'log 1..2') > 0) {
                $return = 0;
            }
            return $return;
        });
        $mock_executer->expects($this->once())
            ->method('getLastOutput')
            ->willReturn([
                'stdout' => "112233 This is the first line\n445566 This is the second line",
            ]);
        $this->cosy->setExecuter($mock_executer);
        $log = $this->cosy->retrieveChangeLog('user/private', json_decode(json_encode(['packages' => [
            [
                'name' => 'user/private',
                'source' => [
                    'type' => 'git',
                    'url' => 'git@github.com:user/private',
                ],
            ],
        ]])), 1, 2);
        self::assertEquals(true, $called);
    }

    public function testClonedPrivateEvenIfProjectGithub()
    {
        $called = false;
        $mock_executer = $this->getMockExecuterWithReturnCallback(function ($command_array) use (&$called) {
            $return = 1;
            $command = implode(' ', $command_array);
            if (strpos($command, 'git clone https://x-token-auth:user-token@bitbucket.org/user/private') === 0) {
                $called = true;
                $return = 0;
            }
            if (strpos($command, 'log 1..2') > 0) {
                $return = 0;
            }
            return $return;
        });
        $mock_executer->expects($this->once())
            ->method('getLastOutput')
            ->willReturn([
                'stdout' => "112233 This is the first line\n445566 This is the second line",
            ]);
        $this->cosy->setExecuter($mock_executer);
        $log = $this->cosy->retrieveChangeLog('user/private', json_decode(json_encode(['packages' => [
            [
                'name' => 'user/private',
                'source' => [
                    'type' => 'git',
                    'url' => 'git@bitbucket.org:user/private',
                ],
            ],
        ]])), 1, 2);
        self::assertEquals(true, $called);
    }
}
