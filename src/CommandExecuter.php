<?php

namespace eiriksm\CosyComposer;

use Psr\Log\LoggerInterface;

class CommandExecuter
{

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \eiriksm\CosyComposer\ProcessFactory
     */
    protected $processFactory;

    protected $cwd;

    protected $output = [];

    /**
     * @var array
     */
    protected $env = [
        'COMPOSER_DISCARD_CHANGES' => 'true',
        'COMPOSER_ALLOW_SUPERUSER' => 'true',
    ];

    public function __construct(LoggerInterface $logger, ProcessFactory $factory)
    {
        $this->logger = $logger;
        $this->processFactory = $factory;
    }

    public function executeCommand($command, $log = true, $timeout = 120, array $env = [])
    {
        if ($log) {
            $this->logger->log('info', new Message('Creating command ' . $command, Message::COMMAND));
        }
        $env = $this->getEnv() + $env + [
            'PATH' => __DIR__ . '/../../../../vendor/bin' . ':' . getenv('PATH'),
        ];
        $process = $this->processFactory->getProcess($command, $this->getCwd(), $env);
        $process->setTimeout($timeout);
        $process->inheritEnvironmentVariables(true);
        $process->run();
        $this->output[] = [
            'stdout' => $process->getOutput(),
            'stderr' => $process->getErrorOutput(),
        ];
        return $process->getExitCode();
    }

    public function getLastOutput()
    {
        $last_index = count($this->output) - 1;
        return $this->output[$last_index];
    }

    /**
     * @return array
     */
    protected function getEnv()
    {
        return $this->env;
    }

    /**
     * @return mixed
     */
    public function getCwd()
    {
        return $this->cwd;
    }

    /**
     * @param mixed $cwd
     */
    public function setCwd($cwd)
    {
        $this->cwd = $cwd;
    }
}
