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

    public function getProcess($commandline, $cwd = null, array $env = null, $input = null, $timeout = 60, array $options = null)
    {
        if (!$cwd) {
            $cwd = $this->getCwd();
        }
        $this->env = $env;
        if ($env) {
            $this->env = $env;
        }
        return new Process($commandline, $cwd, $env);
    }

    /**
     * @return array
     */
    public function getEnv()
    {
        return $this->env ? $this->env : [];
    }

    protected function getCwd()
    {
        return getcwd();
    }
}
