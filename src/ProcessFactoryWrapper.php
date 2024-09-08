<?php

namespace eiriksm\CosyComposer;

use Violinist\ProcessFactory\ProcessFactoryInterface;

class ProcessFactoryWrapper implements ProcessFactoryInterface
{

    /**
     * @var CommandExecuter
     */
    protected $executor;

    /**
     * @return CommandExecuter
     */
    public function getExecutor()
    {
        return $this->executor;
    }

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
    public function getProcess(array $commandline, ?string $cwd = null, ?array $env = null, $input = null, $timeout = 60, array $options = null)
    {
        $execute_wrapper = new ProcessWrapper($commandline, $cwd, $env, $input, $timeout);
        $execute_wrapper->setExecutor($this->executor);
        return $execute_wrapper;
    }
}
