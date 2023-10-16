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
        $string = '{
    "advisories": {
        "drupal/core": {
            "59": {
                "advisoryId": "SA-CORE-2023-006",
                "packageName": "drupal/core",
                "affectedVersions": ">=8.7.0 <9.5.11 || >=10.0 <10.0.11 || >= 10.1 <10.1.4",
                "title": "Drupal core - Critical - Cache poisoning - SA-CORE-2023-006",
                "cve": "CVE-2023-5256",
                "link": "https://www.drupal.org/sa-core-2023-006",
                "reportedAt": "2023-09-20T16:23:05+00:00",
                "sources": [
                    {
                        "name": "Drupal core - Critical - Cache poisoning - SA-CORE-2023-006",
                        "remoteId": "SA-CORE-2023-006"
                    }
                ]
            }
        },
        "psr/log": [
            {
                "advisoryId": "SA-CONTRIB-2023-025",
                "packageName": "drupal/mailchimp",
                "affectedVersions": "<2.2.2",
                "title": "Mailchimp - Critical - Cross Site Request Forgery - SA-CONTRIB-2023-025",
                "cve": null,
                "link": "https://www.drupal.org/sa-contrib-2023-025",
                "reportedAt": "2023-06-28T17:10:15+00:00",
                "sources": [
                    {
                        "name": "Mailchimp - Critical - Cross Site Request Forgery - SA-CONTRIB-2023-025",
                        "remoteId": "SA-CONTRIB-2023-025"
                    }
                ]
            }
        ]
    }
}
';
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