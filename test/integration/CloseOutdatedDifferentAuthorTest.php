<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\Providers\NamedPrs;

/**
 * Documents that outdated PRs are matched (and closed) purely by branch
 * name, regardless of who opened them.
 *
 * BaseUpdater::closeOutdatedPrsForPackage() only looks at the branch name
 * (via a plain strpos() substring check) and the base ref of a pull
 * request. It never inspects the PR author/user, so a pull request opened
 * by a regular human user (not the bot) will still be closed if its branch
 * name happens to match the naming convention violinist uses for updates.
 */
class CloseOutdatedDifferentAuthorTest extends CloseOutdatedBase
{
    protected ?string $packageForUpdateOutput = 'psr/log';
    protected ?string $packageVersionForFromUpdateOutput = '1.0.0';
    protected ?string $packageVersionForToUpdateOutput = '1.1.4';
    protected ?string $composerAssetFiles = 'composer.close.outdated';

    public function setUp() : void
    {
        parent::setUp();
        $this->checkPrUrl = true;
        $this->expectedClosedPrs = [124, 125];
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
                    'ref' => 'psrlog100114',
                ],
            ],
            'psrlog100113' => [
                'number' => 123,
                'title' => 'Test update',
                'base' => [
                    'ref' => 'notmaster',
                    'sha' => 456,
                ],
                'head' => [
                    'ref' => 'psrlog100113',
                ],
            ],
            // This pull request has the exact same branch naming
            // convention as the ones created by the bot, but it was
            // opened by a completely different, human, user. It should
            // still be closed, since the matching is done on branch name
            // alone.
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
                'title' => 'Test update',
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
