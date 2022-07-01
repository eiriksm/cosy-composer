<?php

namespace eiriksm\CosyComposerTest\integration;

use Bitbucket\Api\Repositories;
use Bitbucket\Client;
use eiriksm\CosyComposer\CommandExecuter;
use eiriksm\CosyComposer\CosyComposer;
use eiriksm\CosyComposer\ProviderFactory;
use eiriksm\CosyComposer\Providers\Bitbucket;
use eiriksm\CosyComposer\Providers\Github;
use eiriksm\CosyComposerTest\integration\Base;
use eiriksm\CosyComposerTest\integration\ComposerUpdateIntegrationBase;
use Github\Exception\ValidationFailedException;
use Violinist\Slug\Slug;

/**
 * Test that we are closing PRs not the latest and greatest.
 */
class CloseOutdatedUpdateBranchTest extends CloseOutdatedBase
{
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.1.4';
    protected $composerAssetFiles = 'composer.close.outdated';
    protected $expectedClosedPrs = [123, 124, 125];

    public function setUp()
    {
        parent::setUp();
        $this->mockProvider->method('createPullRequest')
            ->willReturnCallback(function (Slug $slug, array $params) {
                return $this->createPullRequest($slug, $params);
            });
    }

    protected function createPullRequest(Slug $slug, array $params)
    {
        throw new ValidationFailedException('for real');
    }

    protected function getPrsNamed()
    {
        return [
            'psrlog100114' => [
                'number' => 456,
                'title' => 'Test update',
            ],
            'psrlog100113' => [
                'number' => 123,
                'title' => 'Test update',
            ],
            'psrlog100112' => [
                'number' => 124,
                'title' => 'Test update',
            ],
            'psrlog100111' => [
                'number' => 125,
                'title' => 'Test update',
            ]
        ];
    }
}
