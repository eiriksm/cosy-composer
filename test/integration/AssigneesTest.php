<?php

namespace eiriksm\CosyComposerTest\integration;

use Violinist\ProjectData\ProjectData;

/**
 * Test for assignees.
 */
class AssigneesTest extends ComposerUpdateIntegrationBase
{
    protected ?string $composerAssetFiles = 'composer.assignees';
    protected ?string $packageForUpdateOutput = 'psr/log';
    protected ?string $packageVersionForFromUpdateOutput = '1.0.0';
    protected ?string $packageVersionForToUpdateOutput = '1.1.4';
    protected $checkPrUrl = true;

    public function testAssignees()
    {
        $found_assignees = false;
        $project = new ProjectData();
        $project->setRoles(['agency']);
        $this->cosy->setProject($project);
        $this->getMockProvider()->method('createPullRequest')
            ->willReturnCallback(function ($slug, $pr_params) use (&$found_assignees) {
                if (empty($pr_params["assignees"])) {
                    return;
                }
                foreach ($pr_params["assignees"] as $assignee) {
                    foreach (['user1', 'user2'] as $user) {
                        if ($assignee === $user) {
                            continue 2;
                        }
                    }
                    return;
                }
                $found_assignees = true;
            });
        $this->runtestExpectedOutput();
        self::assertTrue($found_assignees);
    }

    /**
     * @dataProvider assigneesProvider
     */
    public function testAssigneesProgrammatic(bool $setting)
    {
        $found_assignees = false;
        $this->cosy->setAssigneesAllowed($setting);
        $this->getMockProvider()->method('createPullRequest')
            ->willReturnCallback(function ($slug, $pr_params) use (&$found_assignees) {
                if (empty($pr_params["assignees"])) {
                    return;
                }
                foreach ($pr_params["assignees"] as $assignee) {
                    foreach (['user1', 'user2'] as $user) {
                        if ($assignee === $user) {
                            continue 2;
                        }
                    }
                    return;
                }
                $found_assignees = true;
            });
        $this->runtestExpectedOutput();
        self::assertEquals($setting, $found_assignees);
    }

    public static function assigneesProvider()
    {
        return [
            [true],
            [false],
        ];
    }
}
