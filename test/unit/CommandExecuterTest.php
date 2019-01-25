<?php

namespace eiriksm\CosyComposerTest\unit;

use eiriksm\CosyComposer\CommandExecuter;
use eiriksm\CosyComposer\Message;
use eiriksm\CosyComposer\ProcessFactory;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

class CommandExecuterTest extends TestCase
{
    public function testExecuteCommand()
    {
        $process_mock = $this->createMock(ProcessFactory::class);
        $logger_mock = $this->createMock(LoggerInterface::class);
        $command = 'echo eirik';
        $called_correctly = false;
        $logger_mock->expects($this->once())
            ->method('log')
            ->willReturnCallback(function ($level, $message) use (&$called_correctly) {
                if ($message->getMessage() == 'Creating command echo eirik'
                    && $message->getType() == Message::COMMAND) {
                    $called_correctly = true;
                }
                return true;
            });
        $ce = new CommandExecuter($logger_mock, $process_mock);
        $process = $this->createMock(Process::class);
        $process->expects($this->once())
            ->method('setTimeout')
            ->with(120);
        $process->expects($this->once())
            ->method('run');
        $process_mock->expects($this->once())
            ->method('getProcess')
            ->willReturn($process);
        $process->expects($this->once())
            ->method('getOutput')
            ->willReturn('STDOUT');
        $process->expects($this->once())
            ->method('getErrorOutput')
            ->willReturn('STDERR');
        $ce->setCwd('eirik');
        $this->assertEquals($ce->getCwd(), 'eirik');
        $ce->executeCommand($command, true);
        $output = $ce->getLastOutput();
        $this->assertEquals('STDOUT', $output['stdout']);
        $this->assertEquals('STDERR', $output['stderr']);
        $this->assertEquals(true, $called_correctly);
    }
}
