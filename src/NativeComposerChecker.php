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
        if (empty($string)) {
            throw new \Exception('No output received from symfony command. This could mean you do not have the symfony command available. This is the stderr: ' . $process->getErrorOutput());
        }
        $json = @json_decode($string, true);
        if (!is_array($json)) {
            throw new \Exception('Invalid JSON found from parsing the security check data');
        }
        $bc_result = [];
        foreach ($json as $type => $packages) {
            foreach ($packages as $package => $items) {
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
}
