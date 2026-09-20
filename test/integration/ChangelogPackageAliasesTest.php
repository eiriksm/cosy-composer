<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * End to end test for the "changelog_package_aliases" violinist config option.
 *
 * Uses the composer.drupal1021* lock fixtures, where drupal/core-recommended
 * is the package that gets updated, but does not itself have a usable
 * changelog. With the changelog_package_aliases config pointing
 * drupal/core-recommended to drupal/core, the changelog should instead be
 * fetched (and linked) using the drupal/core package/repo, including using
 * drupal/core's OWN before/after commit references, since drupal/core and
 * drupal/core-recommended are different git repositories with unrelated
 * commit history.
 */
class ChangelogPackageAliasesTest extends ComposerUpdateIntegrationBase
{
    protected ?string $composerAssetFiles = 'composer.drupal1021-changelog-alias';
    protected ?string $packageForUpdateOutput = 'drupal/core-recommended';
    protected ?string $packageVersionForFromUpdateOutput = '10.2.1';
    protected ?string $packageVersionForToUpdateOutput = '10.2.2';
    protected string $stdout = '';

    // The clone path composer-changelog-fetcher uses is /tmp/md5($package_name).
    // Since the alias resolves drupal/core-recommended to drupal/core, the
    // clone (and the git log command) happens against md5('drupal/core').
    private const CLONE_PATH_FOR_ALIASED_PACKAGE = '/tmp/87801c9c4f0c265caa83fffca9901e08';

    // drupal/core-recommended and drupal/core are different git repositories with
    // unrelated commit history, so the SHAs used for the changelog range have to be
    // drupal/core's OWN before/after references (from its own lock entries), not
    // drupal/core-recommended's. These are drupal/core's references in
    // composer.drupal1021-changelog-alias.lock(.updated).
    private const VERSION_FROM = '0050280087b8ed1fb145fcf22e01ad53c85931db';
    private const VERSION_TO = 'fc9abad1ab687635a5eddec00aa1a5f2a29a23bd';

    public function setUp() : void
    {
        parent::setUp();
        $this->checkPrUrl = true;
    }

    public function testChangelogIsFetchedFromAliasedPackage() : void
    {
        $this->runtestExpectedOutput();
        $this->assertStringContainsString(
            '[fc9abad](https://github.com/drupal/core/commit/fc9abad)',
            $this->prParams['body']
        );
        $this->assertStringNotContainsString('Could not retrieve changelog', $this->prParams['body']);
        $this->assertStringNotContainsString('drupal/core-recommended/commit', $this->prParams['body']);
    }

    public function testChangelogIsNotFetchedFromAliasedPackageWithoutConfig() : void
    {
        // Same fixture data, but without the changelog_package_aliases config,
        // so the changelog is looked up (and fails) for drupal/core-recommended
        // itself, since we never provided a canned git log for that clone path.
        $this->composerAssetFiles = 'composer.drupal1021';
        $this->setUp();
        $this->runtestExpectedOutput();
        $this->assertStringContainsString('Could not retrieve changelog', $this->prParams['body']);
    }

    protected function handleExecutorReturnCallback(array $cmd, &$return) : void
    {
        $this->stdout = '';
        $cmd_string = implode(' ', $cmd);
        $expected_log_command = sprintf(
            'git -C %s log %s..%s --oneline',
            self::CLONE_PATH_FOR_ALIASED_PACKAGE,
            self::VERSION_FROM,
            self::VERSION_TO
        );
        if ($cmd_string === $expected_log_command) {
            $this->stdout = 'fc9abad Drupal 10.2.2
';
        }
    }

    /**
     * @param array<string, string> $output
     */
    protected function processLastOutput(array &$output) : void
    {
        if (!empty($this->stdout)) {
            $output['stdout'] = $this->stdout;
        }
    }
}
