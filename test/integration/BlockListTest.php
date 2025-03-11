<?php

namespace eiriksm\CosyComposerTest\integration;

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
        $this->massageComposerJson($composer_contents);
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
        $this->massageComposerJson($composer_contents);
        file_put_contents(sprintf('%s/composer.json', $this->dir), $composer_contents);
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage('Skipping update of eiriksm/fake-package because it is on the block list by pattern', $this->cosy);
        $this->assertOutputContainsMessage('No updates found', $this->cosy);
    }

    protected function massageComposerJson(&$composer_contents)
    {
        // This is a no-op for now. It's here for the base class.
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
