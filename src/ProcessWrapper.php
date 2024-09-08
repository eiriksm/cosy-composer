<?php

namespace eiriksm\CosyComposer;

use Symfony\Component\Process\Process;

class ProcessWrapper extends Process
{
    protected $ourExitCode;

    /**
     * @var CommandExecuter
     */
    protected $executor;

    protected $line;

    public function __construct(array $command, ?string $cwd = null, ?array $env = null, $input = null, ?float $timeout = 60)
    {
        parent::__construct($command, $cwd, $env, $input, $timeout);
        $this->line = $command;
    }

    /**
     * @param CommandExecuter $executor
     */
    public function setExecutor(CommandExecuter $executor)
    {
        $this->executor = $executor;
    }

    public function run(?callable $callback = null, array $env = []) : int
    {
        $env = 1 < \func_num_args() ? func_get_arg(1) : null;
        if (empty($env)) {
            $env = [];
        }
        $env = array_merge($this->getEnv() ? $this->getEnv() : [], [
            'PATH' => __DIR__ . '/../../../../vendor/bin' . ':' . getenv('PATH'),
        ]);
        $this->ourExitCode = $this->executor->executeCommand($this->line, false, $this->getTimeout(), $env);
        return $this->ourExitCode;
    }

    public function getExitCode()
    {
        return $this->ourExitCode;
    }

    public function getErrorOutput()
    {
        $output = $this->executor->getLastOutput();
        return !empty($output['stderr']) ? $output['stderr'] : '';
    }

    public function getOutput()
    {
        $output = $this->executor->getLastOutput();
        return !empty($output['stdout']) ? $output['stdout'] : '';
    }
}
