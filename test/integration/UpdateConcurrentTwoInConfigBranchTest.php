<?php

namespace eiriksm\CosyComposerTest\integration;

class UpdateConcurrentTwoInConfigBranchTest extends UpdateConcurrentTwoTest
{
    private $configBranchCloneDir;

    public function setUp() : void
    {
        $_ENV['config_branch'] = 'config-branch';
        parent::setUp();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        unset($_ENV['config_branch']);
    }

    public function testUpdateConcurrentWithOutdatedBranch()
    {
        $this->sha = 456;
        $this->runtestExpectedOutput();
        // This means we expect the first package (psr/cache) to be updated, since the PR is out of date. This should
        // show in the messages then.
        $this->assertOutputContainsMessage('Creating pull request from psrcache100101', $this->cosy);
        // Since the max is 1, the second package should not be updated.
        $output = $this->cosy->getOutput();
        $msg = $this->findMessage('Running composer update for package psr/log', $this->cosy);
        self::assertFalse($msg);
    }

    public function testUpdateConcurrentWithUpToDateBranch()
    {
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage('Skipping psr/cache because a pull request already exists', $this->cosy);
        // We have one PR open. Our limit is 1.
        $msg = $this->findMessage('Skipping psr/log because the number of max concurrent PRs (1) seems to have been reached', $this->cosy);
        self::assertNotFalse($msg);
    }

    protected function handleExecutorReturnCallback($cmd, &$return)
    {
        $packages = [
            'psr/log',
            'psr/cache',
        ];
        foreach ($packages as $package) {
            $expected_command = $this->createExpectedCommandForPackage($package);
            if ($expected_command === $cmd) {
                $this->placeComposerLockContentsFromFixture(sprintf('%s.lock.updated', $this->composerAssetFiles), $this->configBranchCloneDir);
            }
        }
        // Also make sure we act on the thing with the config branch being
        // checked out.
        if (!empty($cmd[6]) && $cmd[1] === 'clone' && $cmd[6] === 'config-branch') {
            $this->configBranchCloneDir = $cmd[4];
            mkdir($this->configBranchCloneDir);
            $composer_data = (object) [
                'require' => (object) [
                    'psr/log' => '^1.1',
                    'psr/cache' => '^1.0',
                ],
                'extra' => (object) [
                    'violinist' => (object) [
                        'extends' => 'other.json',
                    ],
                ],
            ];
            $composer_data = json_encode($composer_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $composer_file = sprintf('%s/composer.json', $this->configBranchCloneDir);
            file_put_contents($composer_file, $composer_data);
            // Also create the other.json file.
            $other_json = [
                'number_of_concurrent_updates' => 1,
            ];
            $other_json = json_encode($other_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $other_json_file = sprintf('%s/other.json', $this->configBranchCloneDir);
            file_put_contents($other_json_file, $other_json);
            $this->placeComposerLockContentsFromFixture(sprintf('%s.lock', $this->composerAssetFiles), $this->configBranchCloneDir);
        }
    }

    protected function createComposerFileFromFixtures($dir, $filename)
    {
        // Manually create it, but with one as the limit.
        // Root config that extends shared-violinist-drupal
        $composer_data = (object) [
            'require' => (object) [
                'psr/log' => '^1.1',
                'psr/cache' => '^1.0',
            ],
            'extra' => (object) [
                'violinist' => (object) [
                    'number_of_concurrent_updates' => 2,
                ],
            ],
        ];
        $composer_data = json_encode($composer_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $composer_file = "$dir/composer.json";
        file_put_contents($composer_file, $composer_data);
        $this->placeComposerLockContentsFromFixture(sprintf('%s.lock', $this->composerAssetFiles), $this->dir);
    }
}
