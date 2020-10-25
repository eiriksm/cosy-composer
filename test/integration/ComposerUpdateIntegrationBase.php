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
        $dir = $this->dir;
        $mock_executer = $this->getMockExecuterWithReturnCallback(
            function ($cmd) use ($dir) {
                $return = 0;
                $expected_command = $this->createExpectedCommandForPackage($this->packageForUpdateOutput);
                if ($cmd == $expected_command) {
                    file_put_contents("$dir/composer.lock", file_get_contents(__DIR__ . sprintf('/../fixtures/%s.lock.updated', $this->composerAssetFiles)));
                }
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
            ->willReturn([]);
        $mock_provider_factory->method('createFromHost')
            ->willReturn($mock_provider);

        $this->cosy->setProviderFactory($mock_provider_factory);
        $composer_lock_contents = file_get_contents(__DIR__ . sprintf('/../fixtures/%s.lock', $this->composerAssetFiles));
        file_put_contents("$this->dir/composer.lock", $composer_lock_contents);
        $this->mockProvider = $mock_provider;
    }

    public function runtestExpectedOutput()
    {
        $this->cosy->run();
    }
}
