<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test for using the dev deps config option to 0.
 */
class ExtendsMultipleLevelsTest extends ComposerUpdateIntegrationBase
{
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.1.3';
    protected $packageVersionForToUpdateOutput = '1.1.4';
    protected $composerAssetFiles = 'composer-non-dev';

    public function testExtendsMultipleLevels()
    {
        $this->runtestExpectedOutput();
        $output = $this->cosy->getOutput();
        $this->findMessage('The option number_of_concurrent_updates is set to 5 in the extend config called violinist-base-config.json with the chain "vendor/shared-violinist-drupal" -> "violinist-drupal-config.json" -> "vendor/shared-violinist-common" -> "violinist-base-config.json"', $this->cosy);
    }

    protected function createComposerFileFromFixtures($dir, $filename)
    {
        // Root config that extends shared-violinist-drupal
        $composer_data = (object) [
            'require' => (object) [
                'psr/log' => '^1.1',
            ],
            'extra' => (object) [
                'violinist' => (object) [
                    'extends' => 'vendor/shared-violinist-drupal',
                ],
            ],
        ];
        $composer_contents = json_encode($composer_data);
        $composer_file = "$dir/composer.json";
        file_put_contents($composer_file, $composer_contents);
        // Mock vendor structure
        mkdir("$dir/vendor/vendor/shared-violinist-drupal", 0777, true);
        mkdir("$dir/vendor/vendor/shared-violinist-common", 0777, true);
        copy(__DIR__ . '/../fixtures/level1.json', "$dir/vendor/vendor/shared-violinist-drupal/composer.json");
        copy(__DIR__ . '/../fixtures/violinist-drupal-config.json', "$dir/vendor/vendor/shared-violinist-drupal/violinist-drupal-config.json");
        copy(__DIR__ . '/../fixtures/level2.json', "$dir/vendor/vendor/shared-violinist-common/composer.json");
        copy(__DIR__ . '/../fixtures/violinist-base-config.json', "$dir/vendor/vendor/shared-violinist-common/violinist-base-config.json");
    }
}
