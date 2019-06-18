<?php

namespace eiriksm\CosyComposerTest\unit;

use eiriksm\CosyComposer\CosyLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Wa72\SimpleLogger\ArrayLogger;

class CosyLoggerTest extends TestCase
{
    public function testMethods()
    {
        $logger = new CosyLogger();
        $outer_logger = new ArrayLogger();
        $logger->setLogger($outer_logger);
        $logger->emergency('This is emergency');
        $logger->alert('This is alert');
        $logger->critical('This is critical');
        $logger->error('This is error');
        $logger->warning('This is warning');
        $logger->notice('This is notice');
        $logger->info('This is info');
        $logger->debug('This is debug');
        $messages = $outer_logger->get();
        $this->assertEquals(8, count($messages));
        $this->assertEquals($messages[0]["level"], LogLevel::EMERGENCY);
        $this->assertEquals($messages[4]["level"], LogLevel::WARNING);
    }
}
