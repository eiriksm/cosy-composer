<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\CosyComposer;
use eiriksm\CosyComposer\ProviderFactory;
use eiriksm\CosyComposer\Providers\Github;
use eiriksm\CosyComposerTest\GetCosyTrait;
use eiriksm\CosyComposerTest\GetExecuterTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputDefinition;

abstract class Base extends TestCase
{
    use GetCosyTrait;
    use GetExecuterTrait;

    protected $usesDirect = true;

    protected $defaultSha = 123;

    protected $updateJson;

    /**
     * @var CosyComposer
     */
    protected $cosy;

    /**
     * @var string
     */
    protected $dir;

    protected $mockProvider;

    protected $mockProviderFactory;

    protected $automergeEnabled = false;

    protected $autoMergeParams = [];

    protected $lastCommand = [];

    public function setUp() : void
    {
        $c = $this->getMockCosy();
        $dir = '/tmp/' . uniqid();
        $this->setupDirectory($c, $dir);
        $this->dir = $dir;
        $this->cosy = $c;
    }

    protected function createExpectedCommandForPackage($package)
    {
        return ["composer", 'update', '-n', '--no-ansi', $package, '--with-dependencies'];
    }

    protected function createUpdateJsonFromData($package, $version, $new_version)
    {
        return sprintf('{"installed": [{"name": "%s", "version": "%s", "latest": "%s", "latest-status": "semver-safe-update"}]}', $package, $version, $new_version);
    }

    protected function registerProviderFactory($c)
    {
        $mock_provider_factory = $this->createMock(ProviderFactory::class);
        $mock_provider = $this->createMock(Github::class);
        $mock_provider->method('repoIsPrivate')
            ->willReturn(true);
        $mock_provider->method('getDefaultBranch')
            ->willReturn('master');
        $mock_provider->method('getBranchesFlattened')
            ->willReturn([]);
        $mock_provider->method('getDefaultBase')
            ->willReturnCallback(function () {
                return $this->getDefaultSha();
            });
        $mock_provider->method('getPrsNamed')
            ->willReturn([]);
        $mock_provider_factory->method('createFromHost')
            ->willReturn($mock_provider);
        /** @var CosyComposer $c */
        $c->setProviderFactory($mock_provider_factory);
    }

    protected function getDefaultSha()
    {
        return $this->defaultSha;
    }

    protected function assertOutputContainsMessage($message, $c)
    {
        /** @var CosyComposer $cosy */
        $cosy = $c;
        if ($this->findMessage($message, $cosy)) {
            $this->assertTrue(true, "Message '$message' was found in the output");
            return;
        }
        $this->fail("Message '$message' was not found in output");
    }

    protected function findMessage($message, CosyComposer $c)
    {
        foreach ($c->getOutput() as $output_message) {
            try {
                $this->assertStringContainsString($message, $output_message->getMessage());
                return $output_message;
            } catch (\Exception $e) {
                continue;
            }
        }
        return false;
    }

    protected function placeComposerLockContentsFromFixture($filename, $dir)
    {
        $composer_lock_contents = @file_get_contents(__DIR__ . '/../fixtures/' . $filename);
        if (empty($composer_lock_contents)) {
            return;
        }
        file_put_contents("$dir/composer.lock", $composer_lock_contents);
    }

    protected function placeComposerContentsFromFixture($filename, $dir)
    {
        $composer_contents = file_get_contents(__DIR__ . '/../fixtures/' . $filename);
        $composer_file = "$dir/composer.json";
        file_put_contents($composer_file, $composer_contents);
    }

    protected function createComposerFileFromFixtures($dir, $filename)
    {
        $composer_contents = file_get_contents(__DIR__ . "/../fixtures/$filename");
        $composer_file = "$dir/composer.json";
        file_put_contents($composer_file, $composer_contents);
    }

    protected function setupDirectory(CosyComposer $c, $directory)
    {
        mkdir($directory);
        $c->setTmpDir($directory);
    }

    protected function getMockDefinition()
    {
        // Create a mock app, that can respond to things.
        $mock_definition = $this->createMock(InputDefinition::class);
        $mock_definition->method('getOptions')
            ->willReturn([]);
        return $mock_definition;
    }

    protected function getMockProvider()
    {
        if (!$this->mockProvider) {
            $this->mockProvider = $this->createMock(Github::class);
        }
        return $this->mockProvider;
    }

    protected function getMockProviderFactory()
    {
        if (!$this->mockProviderFactory) {
            $this->mockProviderFactory = $this->createMock(ProviderFactory::class);
        }

        return $this->mockProviderFactory;
    }

    protected function setDummyGithubProvider()
    {
        $mock_provider = $this->getMockProvider();
        $mock_provider->method('repoIsPrivate')
            ->willReturn(true);
        $mock_provider->method('getDefaultBranch')
            ->willReturn('master');
        $mock_provider->method('getBranchesFlattened')
            ->willReturnCallback(function () {
                return $this->getBranchesFlattened();
            });
        $mock_provider->method('getDefaultBase')
            ->willReturnCallback(function () {
                return $this->getDefaultSha();
            });
        $mock_provider->method('getPrsNamed')
            ->willReturnCallback(function () {
                return $this->getPrsNamed();
            });
        $mock_provider->method('enableAutomerge')
            ->willReturnCallback(function ($pr_data, $slug, $merge_method) {
                $this->automergeEnabled = true;
                $this->autoMergeParams = [
                    'pr_data' => $pr_data,
                    'slug' => $slug,
                    'merge_method' => $merge_method,
                ];
                return true;
            });
        $mock_provider_factory = $this->getMockProviderFactory();
        $mock_provider_factory->method('createFromHost')
            ->willReturn($mock_provider);

        $this->cosy->setProviderFactory($mock_provider_factory);
    }

    protected function getBranchesFlattened()
    {
        return [];
    }

    protected function getPrsNamed()
    {
        return [];
    }

    protected function getMockOutputWithUpdate($package, $version_from, $version_to)
    {
        $this->updateJson = $this->createUpdateJsonFromData($package, $version_from, $version_to);
    }

    protected function ensureMockExecuterProvidesLastOutput($mock_executer)
    {
        $mock_executer->method('getLastOutput')
            ->willReturnCallback(function () {
                $last_command_string = implode(' ', $this->lastCommand);
                $output = [
                    'stdout' => '',
                    'stderr' => '',
                ];
                if (mb_strpos($last_command_string, 'composer outdated') === 0) {
                    $output = [
                        'stderr' => '',
                        'stdout' => $this->updateJson,
                    ];
                }
                $this->processLastOutput($output);
                return $output;
            });
    }

    protected function processLastOutput(array &$output)
    {
    }
}
