<?php

namespace eiriksm\CosyComposer\SecurityChecker;

use eiriksm\CosyComposer\SecurityChecker\SecurityCheckerInterface;
use Violinist\ProcessFactory\ProcessFactoryInterface;

class NativeComposerChecker implements SecurityCheckerInterface
{
    /**
     * @var ProcessFactoryInterface
     */
    protected $processFactory;

    public function checkDirectory(string $dir) : array
    {
        // Simply run the composer audit command in the directory in here.
        $command = [
            'composer',
            '--working-dir=' . $dir,
            'audit',
            '--format=json',
        ];
        $process = $this->getProcess($command);
        $process->run();
        // Don't really check the exit code, since it will be non-zero when we
        // have CVEs or whatever.
        $string = $process->getOutput();
        if (empty($string)) {
            throw new \Exception('No output from the composer audit command. This is the stderr: ' . $process->getErrorOutput());
        }
        $json = @json_decode($string, true);
        if (!is_array($json)) {
            throw new \Exception('Invalid JSON found from parsing the security check data');
        }
        // This is called a backwards compatible result because we have to make
        // sure the format of the output matches the one we would have gotten
        // from the one we use on composer 1. Which is kind of a shame, I guess,
        // since the new composer audit command actually is producing JSON that
        // would be totally usable for our case.
        $backwards_compatible_result = [];
        foreach ($json as $type => $packages) {
            foreach ($packages as $package => $items) {
                // If the type is "abandoned" the items will be a string. like so:
                // "abandoned": {
                //   "php-http/message-factory": "psr/http-factory"
                // }
                if (is_string($items)) {
                    continue;
                }
                if (empty($items)) {
                    continue;
                }
                if (empty($backwards_compatible_result[$package])) {
                    $backwards_compatible_result[$package] = [];
                }
                if (empty($backwards_compatible_result[$package][$type])) {
                    $backwards_compatible_result[$package][$type] = [];
                }
                foreach ($items as $item) {
                    $backwards_compatible_result[$package][$type][] = $item;
                }
            }
        }
        return $backwards_compatible_result;
    }

    protected function getProcess(array $command)
    {
        $env = [
            'PATH' => __DIR__ . '/../../../../vendor/bin' . ':' . getenv('PATH'),
        ];
        return $this->getProcessFactory()->getProcess($command, null, $env);
    }

    /**
     * @return mixed
     */
    public function getProcessFactory()
    {
        if (!isset($this->processFactory)) {
            $this->processFactory = new \eiriksm\CosyComposer\ProcessFactory();
        }
        return $this->processFactory;
    }

    /**
     * @param mixed $processFactory
     */
    public function setProcessFactory($processFactory) : void
    {
        $this->processFactory = $processFactory;
    }
}
