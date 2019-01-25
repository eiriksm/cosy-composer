<?php

namespace eiriksm\CosyComposerTest\unit;

use eiriksm\CosyComposer\CommandExecuter;
use eiriksm\CosyComposer\CosyComposer;
use eiriksm\CosyComposerTest\GetCosyTrait;
use eiriksm\CosyComposerTest\GetExecuterTrait;
use GuzzleHttp\Psr7\Response;
use Http\Adapter\Guzzle6\Client;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CosyComposerUnitTest extends TestCase
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
