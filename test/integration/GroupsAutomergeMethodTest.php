<?php

namespace eiriksm\CosyComposerTest\integration;

use Violinist\ProjectData\ProjectData;

class GroupsAutomergeMethodTest extends ComposerUpdateIntegrationBase
{
    protected $composerAssetFiles = 'composer-groups-automerge-method';
    protected $updateJson = <<<JSON
{
    "installed": [
        {
            "name": "psr/log",
            "version": "1.0.0",
            "latest": "1.1.0",
            "latest-status": "semver-safe-update"
        },
        {
            "name": "symfony/console",
            "version": "5.0.0",
            "latest": "5.1.0",
            "latest-status": "semver-safe-update"
        }
    ]
}
JSON;
    protected $hasAutoMerge = true;

    private $autoMergeMethodsByHead = [];

    public function testGroupRulesApplyDifferentAutoMergeMethods()
    {
        $project = new ProjectData();
        $project->setRoles(['agency']);
        $this->cosy->setProject($project);
        $this->getMockProvider()
            ->method('enableAutomerge')
            ->willReturnCallback(function ($pr_data, $slug, $merge_method) {
                $this->automergeEnabled = true;
                $this->autoMergeMethodsByHead[$this->prParams['head']] = $merge_method;
                $this->autoMergeParams = [
                    'pr_data' => $pr_data,
                    'slug' => $slug,
                    'merge_method' => $merge_method,
                ];
                return true;
            });

        $this->runtestExpectedOutput();

        self::assertCount(2, $this->prParamsArray, 'Expected one PR per group/package');
        self::assertArrayHasKey('psrpackages', $this->autoMergeMethodsByHead, 'PSR packages group should have automerge method set');
        self::assertEquals('squash', $this->autoMergeMethodsByHead['psrpackages'], 'PSR packages should use squash method from group rule');

        // Find the symfony console update which should use the project-level default (merge)
        $symfonyHead = null;
        foreach ($this->prParamsArray as $pr) {
            if (strpos($pr['head'], 'symfony') !== false) {
                $symfonyHead = $pr['head'];
                break;
            }
        }

        self::assertNotNull($symfonyHead, 'Should have created a PR for symfony/console');
        self::assertArrayHasKey($symfonyHead, $this->autoMergeMethodsByHead, 'Symfony console should have automerge method set');
        self::assertEquals('merge', $this->autoMergeMethodsByHead[$symfonyHead], 'Symfony console should use project default merge method');
        self::assertNotEquals($this->autoMergeMethodsByHead['psrpackages'], $this->autoMergeMethodsByHead[$symfonyHead], 'Different packages should use different merge methods');
    }

    protected function handleExecutorReturnCallback(array $cmd, &$return)
    {
        if (in_array('composer', $cmd, true) && in_array('update', $cmd, true)) {
            if (in_array('psr/log', $cmd, true)) {
                $this->placeComposerLockContentsFromFixture('composer-groups-automerge-method.lock.updated_psr_log', $this->dir);
            }
            if (in_array('symfony/console', $cmd, true)) {
                $this->placeComposerLockContentsFromFixture('composer-groups-automerge-method.lock.updated_symfony_console', $this->dir);
            }
        }
    }
}
