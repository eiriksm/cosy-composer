<?php

namespace eiriksm\CosyComposerTest\integration;

use Violinist\Slug\Slug;

abstract class ComposerUpdateIntegrationBase extends Base
{

    protected $packageForUpdateOutput;

    protected $packageVersionForFromUpdateOutput;

    protected $packageVersionForToUpdateOutput;

    protected $composerAssetFiles;

    protected $fakePrUrl = 'http://example.com/pr';

    protected $checkPrUrl = false;

    protected $prParams = [];

    protected $hasAutoMerge = false;

    /**
     * @var ?MockObject
     */
    protected $mockProvider = null;

    public function setUp() : void
    {
        parent::setUp();
        if ($this->packageForUpdateOutput) {
            $this->getMockOutputWithUpdate($this->packageForUpdateOutput, $this->packageVersionForFromUpdateOutput, $this->packageVersionForToUpdateOutput);
        }
        if ($this->composerAssetFiles) {
            $this->createComposerFileFromFixtures($this->dir, sprintf('%s.json', $this->composerAssetFiles));
        }
        // Then we are going to mock the provider factory.
        $mock_provider = $this->getMockProvider();
        $mock_executer = $this->getMockExecuterWithReturnCallback(
            function ($cmd) {
                $return = 0;
                $expected_command = $this->createExpectedCommandForPackage($this->packageForUpdateOutput);
                if ($cmd == $expected_command) {
                    $this->placeUpdatedComposerLock();
                }
                $this->handleExecutorReturnCallback($cmd, $return);
                $this->lastCommand = $cmd;
                return $return;
            }
        );
        $this->ensureMockExecuterProvidesLastOutput($mock_executer);
        $this->cosy->setExecuter($mock_executer);
        $this->setDummyGithubProvider();
        $this->placeInitialComposerLock();
        $this->mockProvider = $mock_provider;
        if (method_exists($this->mockProvider, 'method')) {
            $this->mockProvider->method('createPullRequest')
                ->willReturnCallback(function (Slug $slug, array $params) {
                    return $this->createPullRequest($slug, $params);
                });
        }
    }

    public function tearDown(): void
    {
        parent::tearDown();
        putenv('USE_GITHUB_PUBLIC_WRAPPER');
    }

    protected function createPullRequest(Slug $slug, array $params)
    {
        $this->prParams = $params;
        return [
            'number' => 456,
            'html_url' => $this->fakePrUrl,
        ];
    }

    protected function placeInitialComposerLock()
    {
        $this->placeComposerLockContentsFromFixture(sprintf('%s.lock', $this->composerAssetFiles), $this->dir);
    }

    protected function placeUpdatedComposerLock()
    {
        $this->placeComposerLockContentsFromFixture(sprintf('%s.lock.updated', $this->composerAssetFiles), $this->dir);
    }

    protected function handleExecutorReturnCallback($cmd, &$return)
    {
    }

    public function runtestExpectedOutput()
    {
        $this->cosy->run();
        if ($this->checkPrUrl) {
            $this->assertOutputContainsMessage($this->fakePrUrl, $this->cosy);
        }
        self::assertEquals($this->automergeEnabled, $this->hasAutoMerge);
    }
}
