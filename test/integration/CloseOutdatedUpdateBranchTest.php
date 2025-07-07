<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\Providers\NamedPrs;
use Github\Exception\ValidationFailedException;
use Gitlab\Exception\RuntimeException;
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
    private $exceptionClass = ValidationFailedException::class;

    public function setUp() : void
    {
        parent::setUp();
        $this->mockProvider->method('createPullRequest')
            ->willReturnCallback(function (Slug $slug, array $params) {
                return $this->createPullRequest($slug, $params);
            });
    }

    public function testGitlabUpdateBranch()
    {
        $this->exceptionClass = RuntimeException::class;
        $this->testOutdatedClosed();
    }

    protected function createPullRequest(Slug $slug, array $params)
    {
        throw new $this->exceptionClass('for real');
    }

    protected function getPrsNamed() : NamedPrs
    {
        return NamedPrs::createFromArray([
            'psrlog100114' => [
                'number' => 456,
                'title' => 'Test update',
                'base' => [
                    'ref' => 'master',
                    'sha' => 123,
                ],
                'head' => [
                    'ref' => 'psrlog100114'
                ],
            ],
            'psrlog100113' => [
                'number' => 123,
                'title' => 'Test update',
                'head' => [
                    'ref' => 'psrlog100113'
                ],
            ],
            'psrlog100112' => [
                'number' => 124,
                'title' => 'Test update',
                'head' => [
                    'ref' => 'psrlog100112'
                ],
            ],
            'psrlog100111' => [
                'number' => 125,
                'title' => 'Test update',
                'head' => [
                    'ref' => 'psrlog100111'
                ],
            ],
        ]);
    }
}
