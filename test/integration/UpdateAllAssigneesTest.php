<?php

namespace eiriksm\CosyComposerTest\integration;

use Violinist\ProjectData\ProjectData;

class UpdateAllAssigneesTest extends UpdateAllBase
{

    protected $composerJson = 'composer.allow_all_assignees.json';

    public function testUpdateAllAssignees()
    {
        $project = new ProjectData();
        $project->setRoles(['agency']);
        $this->cosy->setProject($project);
        $found_assignees = false;
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
        $this->cosy->run();
        self::assertEquals($found_assignees, true);
    }
}
