<?php

namespace eiriksm\CosyComposer;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

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

    protected ?string $cwd = null;

    /** @var array<int, array{stdout: string, stderr: string}> */
    protected array $output = [];

    /** @var array<string, string> */
    protected array $env = [
        'COMPOSER_DISCARD_CHANGES' => 'true',
        'COMPOSER_ALLOW_SUPERUSER' => 'true',
    ];

    /**
     * Enable or disable ignoring platform requirements for Composer commands.
     *
     * When enabled, sets COMPOSER_IGNORE_PLATFORM_REQS=1 which is equivalent
     * to passing --ignore-platform-reqs to all composer commands.
     */
    public function setIgnorePlatformRequirements(bool $ignore): void
    {
        if ($ignore) {
            $this->env['COMPOSER_IGNORE_PLATFORM_REQS'] = '1';
        } else {
            unset($this->env['COMPOSER_IGNORE_PLATFORM_REQS']);
        }
    }

    public function __construct(LoggerInterface $logger, ProcessFactory $factory)
    {
        $this->logger = $logger;
        $this->processFactory = $factory;
    }

    /**
     * @param array<string> $command
     * @param array<string, string> $env
     */
    public function executeCommand(array $command, bool $log = true, int $timeout = 120, array $env = []): ?int
    {
        if ($log) {
            $this->logger->log('info', new Message('Creating command ' . implode(' ', $command), Message::COMMAND));
        }
        $env = $this->getEnv() + $env + [
            'PATH' => __DIR__ . '/../../../../vendor/bin' . ':' . getenv('PATH'),
        ];
        $process = $this->processFactory->getProcess($command, $this->getCwd(), $env);
        $process->setTimeout($timeout);
        try {
            $process->run();
            $this->output[] = [
                'stdout' => $process->getOutput(),
                'stderr' => $process->getErrorOutput(),
            ];
            return $process->getExitCode();
        } catch (ProcessTimedOutException $e) {
            $process->stop();
            $this->output[] = [
                'stdout' => $process->getOutput(),
                'stderr' => $process->getErrorOutput(),
            ];
            throw $e;
        }
    }

    /**
     * @return array{stdout: string, stderr: string}
     */
    public function getLastOutput(): array
    {
        $last_index = count($this->output) - 1;
        return $this->output[$last_index];
    }

    /**
     * @return array<string, string>
     */
    protected function getEnv(): array
    {
        return $this->env;
    }

    public function getCwd(): ?string
    {
        return $this->cwd;
    }

    public function setCwd(?string $cwd): void
    {
        $this->cwd = $cwd;
    }
}
