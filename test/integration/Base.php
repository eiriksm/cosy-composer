<?php

namespace eiriksm\CosyComposerTest\integration;

use Composer\Console\Application;
use eiriksm\ArrayOutput\ArrayOutput;
use eiriksm\CosyComposer\CommandExecuter;
use eiriksm\CosyComposer\CosyComposer;
use eiriksm\CosyComposerTest\GetCosyTrait;
use eiriksm\CosyComposerTest\GetExecuterTrait;
use PHPUnit\Framework\TestCase;

class Base extends TestCase
{
    use GetCosyTrait;
    use GetExecuterTrait;

    protected function createComposerFileFromFixtures($dir, $filename)
    {
        $composer_contents = file_get_contents(__DIR__ . "/../fixtures/$filename");
        $composer_file = "$dir/composer.json";
        file_put_contents($composer_file, $composer_contents);
    }
}
