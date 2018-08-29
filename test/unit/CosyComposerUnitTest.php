<?php

namespace eiriksm\CosyComposerTest\unit;

use eiriksm\CosyComposer\CommandExecuter;
use eiriksm\CosyComposer\CosyComposer;
use eiriksm\CosyComposerTest\GetCosyTrait;
use eiriksm\CosyComposerTest\GetExecuterTrait;
use GuzzleHttp\Psr7\Response;
use Http\Adapter\Guzzle6\Client;
use Psr\Log\LoggerInterface;

class CosyComposerUnitTest extends \PHPUnit_Framework_TestCase
{
    use GetCosyTrait;
    use GetExecuterTrait;

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
        $c->setCacheDir($bogus_dir);
        $this->assertEquals($bogus_dir, $c->getCacheDir());
    }

    public function testLastStdOut()
    {
        $c = $this->getMockCosy();
        $mock_exec = $this->createMock(CommandExecuter::class);
        $mock_exec->expects($this->once())
            ->method('getLastOutput')
            ->willReturn([
                'stdout' => 'output'
            ]);
        $c->setExecuter($mock_exec);
        $this->assertEquals('output', $c->getLastStdOut());
    }

    public function testCreateTempTokenNoProject()
    {
        $c = $this->getMockCosy();
        $c->setProject(null);
        $this->expectExceptionMessage('No project data was found, so no temp token can be generated.');
        $c->createTempToken();
    }

    public function testCreateTempTokenNoTokenUrl()
    {
        $c = $this->getMockCosy();
        $c->setTokenUrl(null);
        $this->expectExceptionMessage('No token URL specified for project');
        $c->createTempToken();
    }

    public function testCreateTempTokenBadResponse()
    {
        $c = $this->getMockCosy();
        $mock_418_response = new Response(418, [], '{"token":123}');
        $mock_client = $this->createMock(Client::class);
        $mock_client->method('sendRequest')
            ->willReturnOnConsecutiveCalls(
                $mock_418_response
            );
        $c->setHttpClient($mock_client);
        $this->expectExceptionMessage('Wrong status code on temp token request (418).');
        $c->createTempToken();
    }

    /**
     * @dataProvider getComposerJsonVariations
     */
    public function testGetComposerJsonName($json, $input, $expected)
    {
        $this->assertEquals($expected, CosyComposer::getComposerJsonName($json, $input));
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
}