<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * End to end test for the "changelog_package_aliases" violinist config option.
 *
 * Uses the composer.drupal1021* lock fixtures, where drupal/core-recommended
 * is the package that gets updated, but does not itself have a usable
 * changelog. With the changelog_package_aliases config pointing
 * drupal/core-recommended to drupal/core, the changelog should instead be
 * fetched (and linked) using the drupal/core package/repo.
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

    // The source references are drupal/core-recommended's own, since those are
    // what gets looked up before the alias is applied.
    private const VERSION_FROM = 'e4809a6155daf96eb927f59d44e9f26fb5607dbe';
    private const VERSION_TO = 'd8cb769d86449af5ad763f3517c7f3c0e226ed60';

    public function setUp() : void
    {
        parent::setUp();
        $this->checkPrUrl = true;
    }

    public function testChangelogIsFetchedFromAliasedPackage() : void
    {
        $this->runtestExpectedOutput();
        $this->assertStringContainsString(
            '[d8cb769](https://github.com/drupal/core/commit/d8cb769)',
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
            $this->stdout = 'd8cb769 Drupal 10.2.2
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
