<?php

namespace eiriksm\CosyComposerTest\integration;

use Violinist\ProjectData\ProjectData;
use Violinist\Slug\Slug;

class LaravelGroupsLabelsTest extends ComposerUpdateIntegrationBase
{
    protected $composerAssetFiles = 'composer-laravel-groups-labels';
    protected $updateJson = <<<JSON
{
    "installed": [
        {
            "name": "laravel/framework",
            "version": "10.0.0",
            "latest": "10.1.0",
            "latest-status": "semver-safe-update"
        },
        {
            "name": "laravel/telescope",
            "version": "4.0.0",
            "latest": "4.1.0",
            "latest-status": "semver-safe-update"
        },
        {
            "name": "spatie/laravel-permission",
            "version": "5.10.0",
            "latest": "5.11.0",
            "latest-status": "semver-safe-update"
        }
    ]
}
JSON;

    private $labelsByHead = [];

    public function testGroupRulesApplyDifferentLabels()
    {
        $project = new ProjectData();
        $project->setRoles(['agency']);
        $this->cosy->setProject($project);
        $this->getMockProvider()
            ->method('addLabels')
            ->willReturnCallback(function (array $pr_data, Slug $slug, array $labels) {
                $this->labelsByHead[$this->prParams['head']] = $labels;
                return true;
            });

        $this->runtestExpectedOutput();

        self::assertCount(3, $this->prParamsArray, 'Expected one PR per Laravel group rule');
        self::assertArrayHasKey('laravelcorepackages', $this->labelsByHead);
        self::assertArrayHasKey('laraveldevelopmenttools', $this->labelsByHead);
        self::assertArrayHasKey('spatielaravelpackages', $this->labelsByHead);
        self::assertEquals(['laravel-core', 'dependencies'], $this->labelsByHead['laravelcorepackages']);
        self::assertEquals(['laravel-dev', 'dependencies'], $this->labelsByHead['laraveldevelopmenttools']);
        self::assertEquals(['spatie', 'laravel', 'dependencies'], $this->labelsByHead['spatielaravelpackages']);
        self::assertNotEquals($this->labelsByHead['laravelcorepackages'], $this->labelsByHead['laraveldevelopmenttools']);
        self::assertNotEquals($this->labelsByHead['laraveldevelopmenttools'], $this->labelsByHead['spatielaravelpackages']);
    }

    protected function handleExecutorReturnCallback(array $cmd, &$return)
    {
        if (in_array('composer', $cmd, true) && in_array('update', $cmd, true)) {
            if (in_array('laravel/framework', $cmd, true)) {
                $this->placeComposerLockContentsFromFixture('composer-laravel-groups-labels.lock.updated_framework', $this->dir);
            }
            if (in_array('laravel/telescope', $cmd, true)) {
                $this->placeComposerLockContentsFromFixture('composer-laravel-groups-labels.lock.updated_telescope', $this->dir);
            }
            if (in_array('spatie/laravel-permission', $cmd, true)) {
                $this->placeComposerLockContentsFromFixture('composer-laravel-groups-labels.lock.updated_spatie', $this->dir);
            }
        }
    }
}
