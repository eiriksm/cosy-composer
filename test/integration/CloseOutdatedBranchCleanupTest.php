<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\Providers\NamedPrs;
use Violinist\Slug\Slug;

/**
 * Test that branches for superseded Violinist pull requests are cleaned up.
 */
class CloseOutdatedBranchCleanupTest extends CloseOutdatedBase
{
    protected ?string $packageForUpdateOutput = 'psr/log';
    protected ?string $packageVersionForFromUpdateOutput = '1.0.0';
    protected ?string $packageVersionForToUpdateOutput = '1.1.4';
    protected ?string $composerAssetFiles = 'composer.close.outdated';

    private $deletedBranches = [];

    public function setUp() : void
    {
        parent::setUp();
        $this->checkPrUrl = true;
        $this->expectedClosedPrs = [125];
        $this->getMockProvider()->method('getAuthenticatedUsername')
            ->willReturn('violinist-bot');
        $this->getMockProvider()->method('deleteBranch')
            ->willReturnCallback(function (Slug $slug, string $branch_name) {
                $this->deletedBranches[] = $branch_name;
            });
    }

    public function testOutdatedClosed()
    {
        parent::testOutdatedClosed();
        self::assertEquals(['psrlog100111'], $this->deletedBranches);
    }

    protected function getPrsNamed() : NamedPrs
    {
        return NamedPrs::createFromArray([
            'psrlog100114' => [
                'number' => 456,
                'title' => 'Current update',
                'user' => [
                    'login' => 'violinist-bot',
                ],
                'base' => [
                    'ref' => 'master',
                    'sha' => 123,
                ],
                'head' => [
                    'ref' => 'psrlog100114',
                ],
            ],
            'psrlog100112' => [
                'number' => 124,
                'title' => 'A manually opened PR that happens to share a branch name',
                'user' => [
                    'login' => 'some-regular-human-user',
                ],
                'base' => [
                    'ref' => 'master',
                    'sha' => 123,
                ],
                'head' => [
                    'ref' => 'psrlog100112',
                ],
            ],
            'psrlog100111' => [
                'number' => 125,
                'title' => 'Superseded Violinist update',
                'user' => [
                    'login' => 'violinist-bot',
                ],
                'base' => [
                    'ref' => 'master',
                    'sha' => 123,
                ],
                'head' => [
                    'ref' => 'psrlog100111',
                ],
            ],
        ]);
    }
}
