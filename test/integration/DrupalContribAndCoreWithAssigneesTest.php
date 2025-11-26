<?php

namespace eiriksm\CosyComposerTest\integration;

use Violinist\ProjectData\ProjectData;

class DrupalContribAndCoreWithAssigneesTest extends ComposerUpdateIntegrationBase
{
    protected $composerAssetFiles = 'drupal-contrib';
    protected $updateJson = '{"installed": [{"name": "drupal/core-recommended", "version": "11.0.0", "latest": "11.0.1", "latest-status": "semver-safe-update"},{"name": "drupal/coffee", "version": "2.0.0", "latest": "2.0.1", "latest-status": "semver-safe-update"}]}';

    public function testDrupalContribGetAssigneesButCoreDoesNot()
    {
        $project = new ProjectData();
        $project->setRoles(['agency']);
        $this->cosy->setProject($project);
        $this->cosy->setAssigneesAllowed(true);
        $this->runtestExpectedOutput();

        // Should have created 2 PRs
        self::assertCount(2, $this->prParamsArray, 'Expected 2 PRs to be created');

        // Find the contrib and core PRs
        $contribPr = null;
        $corePr = null;
        foreach ($this->prParamsArray as $pr) {
            if ($pr['head'] === 'drupal-contrib') {
                $contribPr = $pr;
            } elseif (str_contains($pr['head'], 'drupalcorerecommended')) {
                $corePr = $pr;
            }
        }

        self::assertNotNull($contribPr, 'Expected to find contrib PR');
        self::assertNotNull($corePr, 'Expected to find core PR');

        // Contrib PR should have assignees
        self::assertNotEmpty($contribPr["assignees"], 'Expected contrib PR to have assignees');
        self::assertContains('drupal-team-lead', $contribPr["assignees"], 'Expected drupal-team-lead in contrib PR assignees');

        // Core PR should NOT have the contrib assignees
        self::assertEmpty($corePr["assignees"], 'Expected core PR to NOT have the contrib group assignees');
    }

    protected function handleExecutorReturnCallback(array $cmd, &$return)
    {
        $command_string = implode(' ', $cmd);
        if (str_contains($command_string, 'composer update')) {
            if (str_contains($command_string, 'drupal/core-recommended')) {
                $this->placeComposerLockContentsFromFixture(sprintf('%s.lock-core', $this->composerAssetFiles), $this->dir);
            }
            if (str_contains($command_string, 'drupal/coffee')) {
                $this->placeComposerLockContentsFromFixture(sprintf('%s.lock-contrib', $this->composerAssetFiles), $this->dir);
            }
        }
    }
}
