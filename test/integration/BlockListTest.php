<?php

namespace eiriksm\CosyComposerTest\integration;

use Composer\Console\Application;
use eiriksm\ArrayOutput\ArrayOutput;
use eiriksm\CosyComposer\CommandExecuter;
use Symfony\Component\Console\Input\InputDefinition;

class BlockListTest extends ComposerUpdateIntegrationBase
{
    protected $packageForUpdateOutput = 'eiriksm/fake-package';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.0.1';

    /**
     * Test that block list works.
     *
     * @dataProvider getBlockListOptions
     */
    public function testNoUpdatesBecauseBlocklisted($opt)
    {
        $composer_contents = '{"require": {"drupal/core": "8.0.0"}, "extra": {"violinist": { "' . $opt . '": ["eiriksm/fake-package"]}}}';
        file_put_contents(sprintf('%s/composer.json', $this->dir), $composer_contents);
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage('Skipping update of eiriksm/fake-package because it is on the block list', $this->cosy);
        $this->assertOutputContainsMessage('No updates found', $this->cosy);
    }

    /**
     * Block list with wildcard should also totally work.
     *
     * @dataProvider getBlockListOptions
     */
    public function testNoUpdatesBecauseBlockListedWildcard($opt)
    {
        $composer_contents = '{"require": {"drupal/core": "8.0.0"}, "extra": {"violinist": { "' . $opt . '": ["eiriksm/*"]}}}';
        file_put_contents(sprintf('%s/composer.json', $this->dir), $composer_contents);
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage('Skipping update of eiriksm/fake-package because it is on the block list by pattern', $this->cosy);
        $this->assertOutputContainsMessage('No updates found', $this->cosy);
    }

    public function getBlockListOptions()
    {
        return [
            [
                'blocklist',
            ],
            [
                // The old deprecated option name that we still support.
                'blacklist',
            ],
        ];
    }
}