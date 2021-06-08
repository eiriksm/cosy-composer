<?php

namespace eiriksm\CosyComposerTest\integration;

use Composer\Console\Application;
use eiriksm\ArrayOutput\ArrayOutput;
use eiriksm\CosyComposer\CommandExecuter;
use eiriksm\CosyComposer\CosyComposer;
use eiriksm\CosyComposer\ProviderFactory;
use eiriksm\CosyComposer\Providers\Github;
use eiriksm\CosyComposerTest\GetCosyTrait;
use eiriksm\CosyComposerTest\GetExecuterTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputDefinition;

abstract class Base extends TestCase
{
    /**
     * @var CosyComposer
     */
    protected $cosy;

    /**
     * @var string
     */
    protected $dir;

    public function setUp()
    {
        $c = $this->getMockCosy();
        $dir = '/tmp/' . uniqid();
        $this->setupDirectory($c, $dir);
        $definition = $this->getMockDefinition();
        $mock_app = $this->getMockApp($definition);
        $c->setApp($mock_app);
        $this->dir = $dir;
        $this->cosy = $c;
    }

    use GetCosyTrait;
    use GetExecuterTrait;

    protected function createExpectedCommandForPackage($package)
    {
        return "composer update -n --no-ansi $package --with-dependencies ";
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
        $default_sha = 123;
        $mock_provider->method('getDefaultBase')
            ->willReturn($default_sha);
        $mock_provider->method('getPrsNamed')
            ->willReturn([]);
        $mock_provider_factory->method('createFromHost')
            ->willReturn($mock_provider);
        /** @var CosyComposer $c */
        $c->setProviderFactory($mock_provider_factory);
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
                $this->assertContains($message, $output_message->getMessage());
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

    protected function getMockApp($mock_definition)
    {
        $mock_app = $this->createMock(Application::class);
        $mock_app->method('getDefinition')
            ->willReturn($mock_definition);
        return $mock_app;
    }

    protected function getMockOutputWithUpdate($package, $version_from, $version_to)
    {
        $mock_output = $this->createMock(ArrayOutput::class);
        $mock_output->method('fetch')
            ->willReturn([
                [
                    $this->createUpdateJsonFromData($package, $version_from, $version_to),
                ]
            ]);
        if ($this->cosy) {
            $this->cosy->setOutput($mock_output);
        }
        return $mock_output;
    }
}
