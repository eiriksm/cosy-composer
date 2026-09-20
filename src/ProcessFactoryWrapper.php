<?php

namespace eiriksm\CosyComposer;

use Violinist\ProcessFactory\ProcessFactoryInterface;

class ProcessFactoryWrapper implements ProcessFactoryInterface
{

    /**
     * The executor.
     *
     * @var CommandExecuter
     */
    protected $executor;

    /**
     * @param CommandExecuter $executor
     */
    public function setExecutor($executor)
    {
        $this->executor = $executor;
    }


    /**
     * Get a process instance.
     *
     * The function signature is the same as the symfony process command.
     *
     * @return \Symfony\Component\Process\Process
     */
    public function getProcess(array $command, ?string $cwd = null, ?array $env = null, $input = null, ?float $timeout = 60)
    {
        $execute_wrapper = new ProcessWrapper($command, $cwd, $env, $input, $timeout);
        $execute_wrapper->setExecutor($this->executor);
        return $execute_wrapper;
    }
}
