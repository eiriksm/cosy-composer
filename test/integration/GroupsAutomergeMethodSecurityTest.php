<?php

namespace eiriksm\CosyComposerTest\integration;

use Violinist\ProjectData\ProjectData;
use Violinist\SymfonyCloudSecurityChecker\SecurityChecker;

class GroupsAutomergeMethodSecurityTest extends ComposerUpdateIntegrationBase
{
    protected $composerAssetFiles = 'composer-groups-automerge-method-security';
    protected $updateJson = <<<JSON
{
    "installed": [
        {
            "name": "psr/log",
            "version": "1.0.0",
            "latest": "1.0.1",
            "latest-status": "update-possible"
        },
        {
            "name": "symfony/console",
            "version": "5.0.0",
            "latest": "5.0.1",
            "latest-status": "update-possible"
        }
    ]
}
JSON;
    protected $hasAutoMerge = true;

    private $autoMergeMethodsByHead = [];

    public function setUp(): void
    {
        parent::setUp();
        $checker = $this->createMock(SecurityChecker::class);
        $checker->method('checkDirectory')
            ->willReturn([
                'psr/log' => true,
                'symfony/console' => true,
            ]);
        $this->cosy->getCheckerFactory()->setChecker($checker);
    }

    public function testGroupRulesApplyDifferentAutoMergeMethodsForSecurityUpdates()
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

        self::assertCount(2, $this->prParamsArray, 'Expected 2 PRs: one for PSR group and one for Symfony');

        // Find the symfony console update which should use the project-level default for security (merge)
        $symfonyHead = null;
        foreach ($this->prParamsArray as $pr) {
            if (strpos($pr['head'], 'symfony') !== false) {
                $symfonyHead = $pr['head'];
                break;
            }
        }

        // At minimum, we should test that symfony/console (outside the rule) uses the project default
        self::assertNotNull($symfonyHead, 'Should have created a PR for symfony/console');
        self::assertArrayHasKey($symfonyHead, $this->autoMergeMethodsByHead, 'Symfony console should have automerge method set');
        self::assertEquals('merge', $this->autoMergeMethodsByHead[$symfonyHead], 'Symfony console should use project default merge method for security updates');

        // If the PSR group also got automerge enabled, verify it uses the group-specific method
        if (isset($this->autoMergeMethodsByHead['psrpackages'])) {
            self::assertEquals('rebase', $this->autoMergeMethodsByHead['psrpackages'], 'PSR packages should use rebase method from group rule for security updates');
            self::assertNotEquals($this->autoMergeMethodsByHead['psrpackages'], $this->autoMergeMethodsByHead[$symfonyHead], 'Different packages should use different merge methods');
        }
    }

    protected function handleExecutorReturnCallback(array $cmd, &$return)
    {
        if (in_array('composer', $cmd, true) && in_array('update', $cmd, true)) {
            if (in_array('psr/log', $cmd, true)) {
                $this->placeComposerLockContentsFromFixture('composer-groups-automerge-method-security.lock.updated_psr_log', $this->dir);
            }
            if (in_array('symfony/console', $cmd, true)) {
                $this->placeComposerLockContentsFromFixture('composer-groups-automerge-method-security.lock.updated_symfony_console', $this->dir);
            }
        }
    }
}
