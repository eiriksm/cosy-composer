<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\Providers\NamedPrs;

/**
 * Asserts that a pull request opened by someone other than the bot is not
 * closed just because its branch name happens to match the naming
 * convention violinist uses for updates.
 *
 * BaseUpdater::closeOutdatedPrsForPackage() currently matches PRs to close
 * purely by branch name (a plain strpos() substring check) and base ref.
 * It never inspects the PR author/user, so this test currently FAILS
 * (PR 124 gets closed even though it belongs to a regular human user),
 * demonstrating the bug. It should start passing once the closing logic
 * is made to also check the PR author.
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
        // PR 124 belongs to a different, human, user and must be left
        // alone. Only 125 (same naming convention, opened by the bot)
        // should be closed.
        $this->expectedClosedPrs = [125];
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
            // NOT be closed.
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
