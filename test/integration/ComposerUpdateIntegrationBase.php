<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\ProviderFactory;
use eiriksm\CosyComposer\Providers\Github;
use PHPUnit\Framework\MockObject\MockObject;
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

    /**
     * @var MockObject
     */
    protected $mockProvider;

    public function setUp()
    {
        parent::setUp();
        if ($this->packageForUpdateOutput) {
            $this->getMockOutputWithUpdate($this->packageForUpdateOutput, $this->packageVersionForFromUpdateOutput, $this->packageVersionForToUpdateOutput);
        }
        if ($this->composerAssetFiles) {
            $this->createComposerFileFromFixtures($this->dir, sprintf('%s.json', $this->composerAssetFiles));
        }
        // Then we are going to mock the provider factory.
        $mock_provider_factory = $this->createMock(ProviderFactory::class);
        $mock_provider = $this->createMock(Github::class);
        $mock_executer = $this->getMockExecuterWithReturnCallback(
            function ($cmd) {
                $return = 0;
                $expected_command = $this->createExpectedCommandForPackage($this->packageForUpdateOutput);
                if ($cmd == $expected_command) {
                    $this->placeUpdatedComposerLock();
                }
                $this->handleExecutorReturnCallback($cmd, $return);
                return $return;
            }
        );
        $this->cosy->setExecuter($mock_executer);
        $slug = new Slug();
        $slug->setProvider('github.com');
        $slug->setSlug('a/b');
        $mock_provider->method('repoIsPrivate')
            ->willReturn(true);
        $mock_provider->method('getDefaultBranch')
            ->willReturn('master');
        $mock_provider->method('getBranchesFlattened')
            ->willReturn([]);
        $default_sha = 123;
        $mock_provider->method('getDefaultBase')
            ->willReturn($default_sha);
        $mock_provider->method('getPrsNamed')
            ->willReturn($this->getPrsNamed());
        $mock_provider_factory->method('createFromHost')
            ->willReturn($mock_provider);

        $this->cosy->setProviderFactory($mock_provider_factory);
        $this->placeInitialComposerLock();
        $this->mockProvider = $mock_provider;
        if ($this->checkPrUrl) {
            $this->mockProvider->method('createPullRequest')
                ->willReturnCallback(function (Slug $slug, array $params) {
                    $this->prParams = $params;
                    return [
                        'html_url' => $this->fakePrUrl,
                    ];
                });
        }
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
  
    protected function getPrsNamed()
    {
        return [];
    }

    public function runtestExpectedOutput()
    {
        $this->cosy->run();
        if ($this->checkPrUrl) {
            $this->assertOutputContainsMessage($this->fakePrUrl, $this->cosy);
        }
    }
}
