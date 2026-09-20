<?php

namespace eiriksm\CosyComposerTest\unit;

use eiriksm\CosyComposer\Updater\IndividualUpdater;
use eiriksm\CosyComposerTest\GetCosyTrait;
use eiriksm\CosyComposerTest\GetExecuterTrait;
use PHPUnit\Framework\TestCase;
use Violinist\Config\Config;

class CosyComposerChangelogTest extends TestCase
{
    use GetExecuterTrait;
    use GetCosyTrait;

    public function testChangeLogPackageNotFound() : void
    {
        $c = $this->getMockCosy();
        // Of course this should not be possible, but what does one do for coverage, eh?
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Did not find the requested package (vendor/package) in the lockfile. This is probably an error');
        $updater = new IndividualUpdater();
        $updater->setSlug($c->getSlug());
        $updater->setAuthentication($c->getUntouchedUserToken());
        $updater->retrieveChangeLog('vendor/package', (object) ['packages' => [], 'packages-dev' => []], 1, 2);
    }

    public function testChangeLogRepoUnknownSource() : void
    {
        $c = $this->getMockCosy();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unknown source or non-git source found for vendor/package. Aborting.');
        $updater = new IndividualUpdater();
        $updater->setSlug($c->getSlug());
        $updater->setAuthentication($c->getUntouchedUserToken());
        $updater->retrieveChangeLog('vendor/package', json_decode(json_encode(['packages' => [
            [
                'name' => 'vendor/package',
            ],
        ]])), 1, 2);
    }

    public function testChangeLogRepoCloneError() : void
    {
        $c = $this->getMockCosy();
        $called = false;
        $mock_executer = $this->getMockExecuterWithReturnCallback(function ($command_array) use (&$called) {
            $command = implode(' ', $command_array);
            if (strpos($command, 'git clone http://example.com/vendor/package /tmp/') === 0) {
                $called = true;
            }
            return 0;
        });
        $c->setExecuter($mock_executer);
        $updater = new IndividualUpdater();
        $updater->setExecuter($mock_executer);
        $updater->setSlug($c->getSlug());
        $updater->setAuthentication($c->getUntouchedUserToken());
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The changelog string was empty for package vendor/package');
        $updater->retrieveChangeLog('vendor/package', json_decode(json_encode(['packages' => [
            [
                'name' => 'vendor/package',
                'source' => [
                    'type' => 'git',
                    'url' => 'http://example.com/vendor/package',
                ],
            ],
        ]])), 1, 2);
        $this->assertEquals(true, $called);
    }

    public function testChangeLogRegular() : void
    {
        $c = $this->getMockCosy();
        $called = false;
        $mock_executer = $this->getMockExecuterWithReturnCallback(function ($command_array) use (&$called) {
            $command = implode(' ', $command_array);
            if (strpos($command, 'log 1..2 --oneline') > 0) {
                $called = true;
            }
            return 0;
        });
        $mock_executer->expects($this->once())
            ->method('getLastOutput')
            ->willReturn([
                'stdout' => "112233 This is the first line\n445566 This is the second line",
                ]);
        $c->setExecuter($mock_executer);
        $updater = new IndividualUpdater();
        $updater->setExecuter($mock_executer);
        $updater->setSlug($c->getSlug());
        $updater->setAuthentication($c->getUntouchedUserToken());
        $log = $updater->retrieveChangeLog('vendor/package', json_decode(json_encode(['packages' => [
            [
                'name' => 'vendor/package',
                'source' => [
                    'type' => 'git',
                    'url' => 'https://github.com/vendor/package',
                ],
            ],
        ]])), 1, 2);
        $this->assertEquals('- [112233](https://github.com/vendor/package/commit/112233) `This is the first line`
- [445566](https://github.com/vendor/package/commit/445566) `This is the second line`
', $log->getAsMarkdown());
        $this->assertEquals(true, $called);
    }

    public function testChangeLogDotGitSuffix() : void
    {
        $c = $this->getMockCosy();
        $mock_executer = $this->getMockExecuterWithReturnCallback(function ($command_array) {
            return 0;
        });
        $mock_executer->expects($this->once())
            ->method('getLastOutput')
            ->willReturn([
                'stdout' => "112233 This is the first line",
            ]);
        $c->setExecuter($mock_executer);
        $updater = new IndividualUpdater();
        $updater->setExecuter($mock_executer);
        $updater->setSlug($c->getSlug());
        $updater->setAuthentication($c->getUntouchedUserToken());
        // URL ends with .git — should be stripped so commit links use the base URL.
        $log = $updater->retrieveChangeLog('vendor/package', json_decode(json_encode(['packages' => [
            [
                'name' => 'vendor/package',
                'source' => [
                    'type' => 'git',
                    'url' => 'https://github.com/vendor/package.git',
                ],
            ],
        ]])), 1, 2);
        $this->assertStringContainsString('https://github.com/vendor/package/commit/112233', $log->getAsMarkdown());
        // Make sure the commit URL does not incorrectly include the ".git" suffix.
        $this->assertStringNotContainsString('https://github.com/vendor/package.git/commit/', $log->getAsMarkdown());
    }

    public function testChangeLogNonDotGitSuffixNotStripped() : void
    {
        $c = $this->getMockCosy();
        $mock_executer = $this->getMockExecuterWithReturnCallback(function ($command_array) {
            return 0;
        });
        $mock_executer->expects($this->once())
            ->method('getLastOutput')
            ->willReturn([
                'stdout' => "112233 This is the first line",
            ]);
        $c->setExecuter($mock_executer);
        $updater = new IndividualUpdater();
        $updater->setExecuter($mock_executer);
        $updater->setSlug($c->getSlug());
        $updater->setAuthentication($c->getUntouchedUserToken());
        // URL ends with "-git" (not ".git") — should NOT be stripped.
        $log = $updater->retrieveChangeLog('vendor/package-git', json_decode(json_encode(['packages' => [
            [
                'name' => 'vendor/package-git',
                'source' => [
                    'type' => 'git',
                    'url' => 'https://github.com/vendor/package-git',
                ],
            ],
        ]])), 1, 2);
        $this->assertStringContainsString('https://github.com/vendor/package-git/commit/112233', $log->getAsMarkdown());
    }

    public function testGetRepoUrlHttps() : void
    {
        $c = $this->getMockCosy();
        $updater = new IndividualUpdater();
        $updater->setSlug($c->getSlug());
        $updater->setAuthentication($c->getUntouchedUserToken());
        $url = $updater->getRepoUrl('vendor/package', json_decode(json_encode(['packages' => [
            [
                'name' => 'vendor/package',
                'source' => [
                    'type' => 'git',
                    'url' => 'https://github.com/vendor/package.git',
                ],
            ],
        ]])));
        $this->assertEquals('https://github.com/vendor/package', $url);
    }

    public function testGetRepoUrlSsh() : void
    {
        $c = $this->getMockCosy();
        $updater = new IndividualUpdater();
        $updater->setSlug($c->getSlug());
        $updater->setAuthentication($c->getUntouchedUserToken());
        $url = $updater->getRepoUrl('vendor/package', json_decode(json_encode(['packages' => [
            [
                'name' => 'vendor/package',
                'source' => [
                    'type' => 'git',
                    'url' => 'git@github.com:vendor/package.git',
                ],
            ],
        ]])));
        $this->assertEquals('https://github.com/vendor/package', $url);
    }

    public function testGetRepoUrlBitbucketSsh() : void
    {
        $c = $this->getMockCosy();
        $updater = new IndividualUpdater();
        $updater->setSlug($c->getSlug());
        $updater->setAuthentication($c->getUntouchedUserToken());
        $url = $updater->getRepoUrl('vendor/package', json_decode(json_encode(['packages' => [
            [
                'name' => 'vendor/package',
                'source' => [
                    'type' => 'git',
                    'url' => 'git@bitbucket.org:vendor/package.git',
                ],
            ],
        ]])));
        $this->assertEquals('https://bitbucket.org/vendor/package', $url);
    }

    public function testGetRepoUrlGitlabSsh() : void
    {
        $c = $this->getMockCosy();
        $updater = new IndividualUpdater();
        $updater->setSlug($c->getSlug());
        $updater->setAuthentication($c->getUntouchedUserToken());
        $url = $updater->getRepoUrl('vendor/package', json_decode(json_encode(['packages' => [
            [
                'name' => 'vendor/package',
                'source' => [
                    'type' => 'git',
                    'url' => 'git@gitlab.com:vendor/package.git',
                ],
            ],
        ]])));
        $this->assertEquals('https://gitlab.com/vendor/package', $url);
    }

    public function testChangeLogBitbucketSshUrl() : void
    {
        $c = $this->getMockCosy();
        $mock_executer = $this->getMockExecuterWithReturnCallback(function ($command_array) {
            return 0;
        });
        $mock_executer->expects($this->once())
            ->method('getLastOutput')
            ->willReturn([
                'stdout' => "112233 This is the first line",
            ]);
        $c->setExecuter($mock_executer);
        $updater = new IndividualUpdater();
        $updater->setExecuter($mock_executer);
        $updater->setSlug($c->getSlug());
        $updater->setAuthentication($c->getUntouchedUserToken());
        $log = $updater->retrieveChangeLog('vendor/package', json_decode(json_encode(['packages' => [
            [
                'name' => 'vendor/package',
                'source' => [
                    'type' => 'git',
                    'url' => 'git@bitbucket.org:vendor/package.git',
                ],
            ],
        ]])), 1, 2);
        $this->assertStringContainsString('https://bitbucket.org/vendor/package/commits/112233', $log->getAsMarkdown());
        $this->assertStringNotContainsString('git@bitbucket.org', $log->getAsMarkdown());
    }

    public function testChangeLogGitlabSshUrl() : void
    {
        $c = $this->getMockCosy();
        $mock_executer = $this->getMockExecuterWithReturnCallback(function ($command_array) {
            return 0;
        });
        $mock_executer->expects($this->once())
            ->method('getLastOutput')
            ->willReturn([
                'stdout' => "112233 This is the first line",
            ]);
        $c->setExecuter($mock_executer);
        $updater = new IndividualUpdater();
        $updater->setExecuter($mock_executer);
        $updater->setSlug($c->getSlug());
        $updater->setAuthentication($c->getUntouchedUserToken());
        $log = $updater->retrieveChangeLog('vendor/package', json_decode(json_encode(['packages' => [
            [
                'name' => 'vendor/package',
                'source' => [
                    'type' => 'git',
                    'url' => 'git@gitlab.com:vendor/package.git',
                ],
            ],
        ]])), 1, 2);
        $this->assertStringContainsString('https://gitlab.com/vendor/package/-/commit/112233', $log->getAsMarkdown());
        $this->assertStringNotContainsString('git@gitlab.com', $log->getAsMarkdown());
    }

    public function testGetRepoUrlNoSource() : void
    {
        $c = $this->getMockCosy();
        $updater = new IndividualUpdater();
        $updater->setSlug($c->getSlug());
        $updater->setAuthentication($c->getUntouchedUserToken());
        $url = $updater->getRepoUrl('vendor/package', json_decode(json_encode(['packages' => [
            [
                'name' => 'vendor/package',
            ],
        ]])));
        $this->assertNull($url);
    }

    public function testChangeLogSshUrl() : void
    {
        $c = $this->getMockCosy();
        $mock_executer = $this->getMockExecuterWithReturnCallback(function ($command_array) {
            return 0;
        });
        $mock_executer->expects($this->once())
            ->method('getLastOutput')
            ->willReturn([
                'stdout' => "112233 This is the first line\n445566 This is the second line",
            ]);
        $c->setExecuter($mock_executer);
        $updater = new IndividualUpdater();
        $updater->setExecuter($mock_executer);
        $updater->setSlug($c->getSlug());
        $updater->setAuthentication($c->getUntouchedUserToken());
        // Use an SSH git URL — the commit links in the markdown should still use HTTPS.
        $log = $updater->retrieveChangeLog('vendor/package', json_decode(json_encode(['packages' => [
            [
                'name' => 'vendor/package',
                'source' => [
                    'type' => 'git',
                    'url' => 'git@github.com:vendor/package.git',
                ],
            ],
        ]])), 1, 2);
        $this->assertStringContainsString('https://github.com/vendor/package/commit/112233', $log->getAsMarkdown());
        $this->assertStringContainsString('https://github.com/vendor/package/commit/445566', $log->getAsMarkdown());
        // Make sure it doesn't contain the SSH-style URL in the commit links.
        $this->assertStringNotContainsString('git@github.com', $log->getAsMarkdown());
    }

    public function testChangeLogSuperLong() : void
    {
        $c = $this->getMockCosy();
        $called = false;
        $mock_executer = $this->getMockExecuterWithReturnCallback(function ($command_array) use (&$called) {
            $command = implode(' ', $command_array);
            if (strpos($command, 'log 1..2 --oneline') > 0) {
                $called = true;
            }
            return 0;
        });
        // Use the one-line output of a comparison between Drupal 8.4 and 8.5.
        $one_line_example_output = file_get_contents(__DIR__ . '/../fixtures/git-log-one-line-super-long.txt');
        $mock_executer->expects($this->once())
            ->method('getLastOutput')
            ->willReturn([
                'stdout' => $one_line_example_output,
            ]);
        $c->setExecuter($mock_executer);
        $updater = new IndividualUpdater();
        $updater->setExecuter($mock_executer);
        $updater->setSlug($c->getSlug());
        $updater->setAuthentication($c->getUntouchedUserToken());
        $log = $updater->retrieveChangeLog('drupal/core', json_decode(json_encode(['packages' => [
            [
                'name' => 'drupal/core',
                'source' => [
                    'type' => 'git',
                    'url' => 'https://github.com/drupal/core',
                ],
            ],
        ]])), 1, 2);
        $this->assertEquals(file_get_contents(__DIR__ . '/../fixtures/git-log-one-line-super-long-markdown.txt'), $log->getAsMarkdown());
        $this->assertEquals(true, $called);
    }

    public function testChangeLogDoesNotAliasPackageNameItself() : void
    {
        // retrieveChangeLog() never resolves changelog_package_aliases on its own:
        // that is resolveChangelogAlias()'s job, called by the updater before
        // retrieveChangeLog(). So looking up drupal/core-recommended in a lockfile
        // that only has drupal/core fails here, regardless of any alias config.
        $c = $this->getMockCosy();
        $mock_executer = $this->getMockExecuterWithReturnCallback(function ($command_array) {
            return 0;
        });
        $updater = new IndividualUpdater();
        $updater->setExecuter($mock_executer);
        $updater->setSlug($c->getSlug());
        $updater->setAuthentication($c->getUntouchedUserToken());
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Did not find the requested package (drupal/core-recommended) in the lockfile. This is probably an error');
        $updater->retrieveChangeLog('drupal/core-recommended', json_decode(json_encode(['packages' => [
            [
                'name' => 'drupal/core',
                'source' => [
                    'type' => 'git',
                    'url' => 'https://github.com/drupal/core',
                ],
            ],
        ], 'packages-dev' => []])), 1, 2);
    }

    /**
     * @param mixed ...$args
     * @return mixed
     */
    private function invokeResolveChangelogAlias(IndividualUpdater $updater, ...$args)
    {
        $method = new \ReflectionMethod($updater, 'resolveChangelogAlias');
        $method->setAccessible(true);
        return $method->invoke($updater, ...$args);
    }

    public function testResolveChangelogAliasWithoutConfigReturnsOriginal() : void
    {
        $updater = new IndividualUpdater();
        $pre_data = (object) ['source' => (object) ['reference' => 'aaa']];
        $post_data = (object) ['source' => (object) ['reference' => 'bbb']];
        $result = $this->invokeResolveChangelogAlias(
            $updater,
            'vendor/package',
            (object) ['packages' => [], 'packages-dev' => []],
            (object) ['packages' => [], 'packages-dev' => []],
            $pre_data,
            $post_data,
            null
        );
        $this->assertSame(['vendor/package', $pre_data, $post_data], $result);
    }

    public function testResolveChangelogAliasUsesAliasedPackageOwnReferences() : void
    {
        // drupal/core-recommended and drupal/core are different git repositories.
        // The SHAs looked up for the ORIGINAL (unaliased) package must not be reused
        // for the aliased one: its own before/after references have to be looked up
        // instead, since the original package's SHAs would not resolve in the
        // aliased package's repository.
        $updater = new IndividualUpdater();
        $config = Config::createFromComposerData(json_decode(json_encode([
            'extra' => [
                'violinist' => [
                    'changelog_package_aliases' => [
                        'drupal/core-recommended' => 'drupal/core',
                    ],
                ],
            ],
        ])));
        $lockdata = json_decode(json_encode(['packages' => [
            [
                'name' => 'drupal/core-recommended',
                'source' => ['type' => 'git', 'url' => 'https://github.com/drupal/core-recommended', 'reference' => 'recommended-before'],
            ],
            [
                'name' => 'drupal/core',
                'source' => ['type' => 'git', 'url' => 'https://github.com/drupal/core', 'reference' => 'core-before'],
            ],
        ], 'packages-dev' => []]));
        $new_lockdata = json_decode(json_encode(['packages' => [
            [
                'name' => 'drupal/core-recommended',
                'source' => ['type' => 'git', 'url' => 'https://github.com/drupal/core-recommended', 'reference' => 'recommended-after'],
            ],
            [
                'name' => 'drupal/core',
                'source' => ['type' => 'git', 'url' => 'https://github.com/drupal/core', 'reference' => 'core-after'],
            ],
        ], 'packages-dev' => []]));
        // What the caller would have looked up for the updated (unaliased) package itself.
        $original_pre_data = (object) ['source' => (object) ['reference' => 'recommended-before']];
        $original_post_data = (object) ['source' => (object) ['reference' => 'recommended-after']];
        [$resolved_name, $resolved_pre, $resolved_post] = $this->invokeResolveChangelogAlias(
            $updater,
            'drupal/core-recommended',
            $lockdata,
            $new_lockdata,
            $original_pre_data,
            $original_post_data,
            $config
        );
        $this->assertEquals('drupal/core', $resolved_name);
        $this->assertEquals('core-before', $resolved_pre->source->reference);
        $this->assertEquals('core-after', $resolved_post->source->reference);
    }

    public function testResolveChangelogAliasFallsBackWhenAliasedPackageMissing() : void
    {
        $updater = new IndividualUpdater();
        $config = Config::createFromComposerData(json_decode(json_encode([
            'extra' => [
                'violinist' => [
                    'changelog_package_aliases' => [
                        'vendor/package-metapackage' => 'vendor/package',
                    ],
                ],
            ],
        ])));
        // Neither lock file actually has vendor/package.
        $lockdata = json_decode(json_encode(['packages' => [], 'packages-dev' => []]));
        $new_lockdata = json_decode(json_encode(['packages' => [], 'packages-dev' => []]));
        $pre_data = (object) ['source' => (object) ['reference' => 'aaa']];
        $post_data = (object) ['source' => (object) ['reference' => 'bbb']];
        $result = $this->invokeResolveChangelogAlias(
            $updater,
            'vendor/package-metapackage',
            $lockdata,
            $new_lockdata,
            $pre_data,
            $post_data,
            $config
        );
        $this->assertSame(['vendor/package-metapackage', $pre_data, $post_data], $result);
    }
}
