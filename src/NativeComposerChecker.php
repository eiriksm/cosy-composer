<?php

namespace eiriksm\CosyComposer;

use Violinist\SymfonyCloudSecurityChecker\SecurityChecker;

class NativeComposerChecker extends SecurityChecker
{
    public function checkDirectory($dir)
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
        // If the process is saying we do not know the command "audit" then that
        // means we are using composer 1, which is not great. In those cases we
        // try to just use the "old" checker I guess.
        if (strpos($process->getErrorOutput(), 'Command "audit" is not defined') !== false) {
            return parent::checkDirectory($dir);
        }
        if (empty($string)) {
            throw new \Exception('No output from the composer audit command. This is the stderr: ' . $process->getErrorOutput());
        }
        $json = @json_decode($string, true);
        if (!is_array($json)) {
            throw new \Exception('Invalid JSON found from parsing the security check data');
        }
        $bc_result = [];
        foreach ($json as $type => $packages) {
            foreach ($packages as $package => $items) {
                // If the type is "abandoned" the items will be a string. like so:
                // "abandoned": {
                //   "php-http/message-factory": "psr/http-factory"
                // }
                if (is_string($items)) {
                    continue;
                }
                if (empty($bc_result[$package])) {
                    $bc_result[$package] = [];
                }
                if (empty($bc_result[$package][$type])) {
                    $bc_result[$package][$type] = [];
                }
                foreach ($items as $item) {
                    $bc_result[$package][$type][] = $item;
                }
            }
        }
        return $bc_result;
    }

    protected function getProcess(array $command)
    {
        $env = [
            'PATH' => __DIR__ . '/../../../../vendor/bin' . ':' . getenv('PATH'),
        ];
        return $this->getProcessFactory()->getProcess($command, null, $env);
    }
}
