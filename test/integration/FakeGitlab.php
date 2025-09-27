<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\Providers\Gitlab;
use eiriksm\CosyComposer\Providers\NamedPrs;
use Violinist\Slug\Slug;

class FakeGitlab extends Gitlab
{

    public function getClient()
    {
        return $this->client;
    }

    public function getdefaultBranch($slug)
    {
        return 'main';
    }

    public function getDefaultBase(Slug $slug, $default_branch)
    {
        return 'abab';
    }

    public function getPrsNamed(Slug $slug): NamedPrs
    {
        return NamedPrs::createFromArray([
            'drushdrush9721036' => [
                'base' => [
                    'sha' => 'fefe',
                ],
                'number' => 123,
                'title' => 'Not update drush, thats for sure. This will trigger an update of the PR',
                'head' => [
                    'ref' => 'drushdrush9721036',
                ],
            ],
        ]);
    }

    public function getBranchesFlattened(Slug $slug)
    {
        return ['drushdrush9721036'];
    }
}
