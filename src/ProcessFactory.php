<?php

namespace eiriksm\CosyComposer;

use Symfony\Component\Process\Process;
use Violinist\ProcessFactory\ProcessFactoryInterface;

class ProcessFactory implements ProcessFactoryInterface
{

    /**
     * @var array
     */
    protected $env;

    public function getProcess(array $command, ?string $cwd = null, ?array $env = null, $input = null, ?float $timeout = 60)
    {
        if (!$cwd) {
            $cwd = $this->getCwd();
        }
        $this->env = $env;
        return new Process($command, $cwd, $env);
    }

    /**
     * @return array
     */
    public function getEnv()
    {
        return $this->env ?: [];
    }

    protected function getCwd()
    {
        return getcwd();
    }
}
