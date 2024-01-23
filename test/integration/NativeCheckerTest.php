<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\NativeComposerChecker;
use Symfony\Component\Process\Process;
use Violinist\ProcessFactory\ProcessFactoryInterface;

class NativeCheckerTest extends ComposerUpdateIntegrationBase
{
    protected $composerAssetFiles = 'composer.drupal1021';
    protected $packageForUpdateOutput = 'drupal/core-recommended';
    protected $packageVersionForFromUpdateOutput = '10.2.1';
    protected $packageVersionForToUpdateOutput = '10.2.2';

    public function testNativeChecker()
    {
        $native_checker = new NativeComposerChecker();
        $mock_process_factory = $this->createMock(ProcessFactoryInterface::class);
        $mock_process = $this->createMock(Process::class);
        $mock_process_factory->method('getProcess')
            ->willReturn($mock_process);
        $mock_process->method('getOutput')
            ->willReturnCallback(function () {
                return '{
    "advisories": {
        "drupal/core": {
            "60": {
                "advisoryId": "SA-CORE-2024-001",
                "packageName": "drupal/core",
                "affectedVersions": ">=8.0 <10.1.8 || >=10.2 <10.2.2",
                "title": "Drupal core - Moderately critical - Denial of Service - SA-CORE-2024-001",
                "cve": null,
                "link": "https://www.drupal.org/sa-core-2024-001",
                "reportedAt": "2024-01-17T17:04:39+00:00",
                "sources": [
                    {
                        "name": "Drupal core - Moderately critical - Denial of Service - SA-CORE-2024-001",
                        "remoteId": "SA-CORE-2024-001"
                    }
                ]
            }
        }
    }
}';
            });
        $native_checker->setProcessFactory($mock_process_factory);
        $this->cosy->getCheckerFactory()->setChecker($native_checker);
        $this->runtestExpectedOutput();
        self::assertEquals('[SECURITY] Update drupal/core-recommended from 10.2.1 to 10.2.2', $this->prParams["title"]);
    }
}
