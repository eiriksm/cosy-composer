<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\Providers\NamedPrs;
use Github\Exception\ValidationFailedException;
use Violinist\Slug\Slug;
use Violinist\ProjectData\ProjectData;

/**
 * Test for labels being enabled.
 */
abstract class LabelTestBase extends ComposerUpdateIntegrationBase
{
    protected $isUpdate = false;

    protected $labelsAdded = [];
    protected $expectedLabelAdding = false;

    protected function getBranchesFlattened()
    {
        if (!$this->isUpdate) {
            return [];
        }
        return ['psrlog113114'];
    }

    protected function createPullRequest(Slug $slug, array $params)
    {
        if (!$this->isUpdate) {
            return parent::createPullRequest($slug, $params);
        }
        throw new ValidationFailedException('I want you to update please');
    }

    protected function getPrsNamed() : NamedPrs
    {
        if (!$this->isUpdate) {
            return NamedPrs::createFromArray([]);
        }
        return NamedPrs::createFromArray([
            'psrlog113114' => [
                'base' => [
                    'sha' => 456,
                ],
                'title' => 'not the same as the other',
                'number' => 666,
                'head' => [
                    'ref' => 'psrlog113114',
                ],
            ],
        ]);
    }

    /**
     * @dataProvider getUpdateVariations
     */
    public function testLabels($should_have_updated)
    {
        $project = new ProjectData();
        $project->setRoles(['agency']);
        $this->cosy->setProject($project);
        $this->getMockProvider()
            ->method('addLabels')
            ->willReturnCallback(function (array $pr_data, Slug $slug, array $labels) {
                $this->labelsAdded = $labels;
                return true;
            });
        $this->isUpdate = $should_have_updated;
        $this->checkPrUrl = !$should_have_updated;
        $this->runtestExpectedOutput();
        self::assertEquals($this->expectedLabelAdding, !empty($this->labelsAdded));
    }

    public function getUpdateVariations()
    {
        return [
            [true],
            [false],
        ];
    }
}
