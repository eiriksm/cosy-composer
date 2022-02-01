<?php

namespace eiriksm\CosyComposerTest\integration;

use Github\Exception\ValidationFailedException;
use Violinist\ProjectData\ProjectData;
use Violinist\Slug\Slug;

class UpdateExistingWithAssigneesTest extends ComposerUpdateIntegrationBase
{

    protected $packageForUpdateOutput = 'drush/drush';
    protected $packageVersionForFromUpdateOutput = '9.7.2';
    protected $packageVersionForToUpdateOutput = '10.3.6';
    protected $composerAssetFiles = 'composer.update_assignees';

    public function testRemovalsInPackagesUpdated()
    {
        $project = new ProjectData();
        $project->setRoles(['agency']);
        $this->cosy->setProject($project);
        $update_called = false;
        $this->getMockProvider()->method('createPullRequest')
            ->willReturnCallback(function ($slug, $pr_params) {
                $this->prParams = $pr_params;
                throw new ValidationFailedException('We are faking a PR exists');
            });
        $this->getMockProvider()->method('updatePullRequest')
            ->willReturnCallback(function (Slug $slug, $id, $params) use (&$update_called) {
                $this->prParams = $params;
                $update_called = true;
            });
        $this->runtestExpectedOutput();
        self::assertTrue($update_called);
        self::assertNotEmpty($this->prParams["assignees"]);
    }

    protected function getPrsNamed()
    {
        return [
            'drushdrush9721036' => [
                'number' => 123,
                'title' => 'Not update drush, thats for sure. This will trigger an update of the PR',
            ]
        ];
    }
}
