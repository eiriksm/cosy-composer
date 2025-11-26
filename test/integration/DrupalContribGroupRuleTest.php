<?php

namespace eiriksm\CosyComposerTest\integration;

use Violinist\ProjectData\ProjectData;

class DrupalContribGroupRuleTest extends ComposerUpdateIntegrationBase
{
    protected $composerAssetFiles = 'drupal-contrib';
    protected $packageForUpdateOutput = 'drupal/coffee';
    protected $packageVersionForFromUpdateOutput = '2.0.0';
    protected $packageVersionForToUpdateOutput = '2.0.1';
    protected $checkPrUrl = true;

    public function testDrupalContribGroup()
    {
        $this->runtestExpectedOutput();
        self::assertEquals('drupal-contrib', $this->prParams['head']);
        self::assertEquals('Update group `Drupal Contrib Modules`', $this->prParams["title"]);
    }

    public function testDrupalContribGroupWithAssignees()
    {
        $project = new ProjectData();
        $project->setRoles(['agency']);
        $this->cosy->setProject($project);
        $this->cosy->setAssigneesAllowed(true);
        $this->runtestExpectedOutput();
        self::assertEquals('drupal-contrib', $this->prParams['head']);
        self::assertEquals('Update group `Drupal Contrib Modules`', $this->prParams["title"]);
        self::assertNotEmpty($this->prParams["assignees"], 'Expected assignees to be set');
        self::assertContains('drupal-team-lead', $this->prParams["assignees"], 'Expected drupal-team-lead to be in assignees');
    }
}
