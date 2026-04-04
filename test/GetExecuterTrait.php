<?php

namespace eiriksm\CosyComposerTest;

use eiriksm\CosyComposer\CommandExecuter;

trait GetExecuterTrait
{

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject&CommandExecuter
     */
    protected function getMockExecuterWithReturnCallback(callable $function)
    {
        $mock_executer = $this->createMock(CommandExecuter::class);
        $mock_executer->method('executeCommand')
            ->will($this->returnCallback($function));
        return $mock_executer;
    }
}
