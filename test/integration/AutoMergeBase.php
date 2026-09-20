<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\Providers\NamedPrs;
use Github\Exception\ValidationFailedException;
use Violinist\Slug\Slug;

abstract class AutoMergeBase extends ComposerUpdateIntegrationBase
{
    protected $isUpdate = false;

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
                'head' => [
                    'ref' => 'psrlog113114',
                ],
                'title' => 'not the same as the other',
                'number' => 666,
            ],
        ]);
    }

    /**
     * @dataProvider getUpdateVariations
     */
    public function testAutomerge($should_have_updated)
    {
        $this->isUpdate = $should_have_updated;
        $this->checkPrUrl = !$should_have_updated;
        $this->runtestExpectedOutput();
    }

    public function getUpdateVariations()
    {
        return [
            [true],
            [false],
        ];
    }
}
