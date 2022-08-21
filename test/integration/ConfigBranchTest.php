<?php

namespace eiriksm\CosyComposerTest\integration;

use Bitbucket\Api\Repositories;
use Bitbucket\Client;
use eiriksm\CosyComposer\CommandExecuter;
use eiriksm\CosyComposer\CosyComposer;
use eiriksm\CosyComposer\ProviderFactory;
use eiriksm\CosyComposer\Providers\Bitbucket;
use eiriksm\CosyComposer\Providers\Github;
use eiriksm\CosyComposerTest\integration\Base;
use eiriksm\CosyComposerTest\integration\ComposerUpdateIntegrationBase;
use Violinist\ProjectData\ProjectData;

/**
 * Test for setting a project config branch.
 */
class ConfigBranchTest extends ComposerUpdateIntegrationBase
{
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.1.4';
    protected $composerAssetFiles = 'empty';

    public function tearDown() : void
    {
        parent::tearDown();
        putenv('config_branch');
        unset($_ENV['config_branch']);
    }

    /**
     * @dataProvider getEnvVariations
     */
    public function testConfigBranch($env)
    {
        $project = new ProjectData();
        $project->setEnvString($env);
        $this->cosy->setProject($project);
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage('Changing to config branch: config', $this->cosy);
    }

    public function getEnvVariations()
    {
        return [
            ["config_branch=config"],
            ["PATH=/tmp\nconfig_branch=config"],
            ['config_branch=config
derp=true'],
            [' config_branch=other_config 
config_branch=config'],
            ["\n\nconfig_branch=config\n\n\nconfig_branch=other_config"],
            ["\n\nHOME=/tmp\n\n\nconfig_branch=config"],
        ];
    }

    protected function handleExecutorReturnCallback($cmd, &$return)
    {
        $cmd_string = implode(' ', $cmd);
        if (!preg_match('/git clone --depth=1 https:\/\/user-token:@github.com\/a\/b .* config/', $cmd_string, $output_array)) {
            return;
        }
        // Now retrieve the dir.
        $dir = str_replace(' -b config', '', str_replace('git clone --depth=1 https://user-token:@github.com/a/b ', '', $cmd));
        mkdir($dir);
        $this->placeComposerContentsFromFixture('empty.json', $dir);
    }
}
