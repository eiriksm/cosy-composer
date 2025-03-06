<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\CosyComposer;
use eiriksm\CosyComposer\Helpers;
use Symfony\Component\Yaml\Yaml;

/**
 * Test for a default commit message.
 */
class CommitMessageTest extends ComposerUpdateIntegrationBase
{
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.0.2';
    protected $composerAssetFiles = 'composer-commit';
    protected $hasCorrectCommit = false;
    protected $commitCommand = '';

    public function tearDown() : void
    {
        putenv('USE_NEW_COMMIT_MSG');
    }
    
    public function testCommitMessage()
    {
        $this->runtestExpectedOutput();
        self::assertEquals($this->hasCorrectCommit, true);
    }

    public function testNewCommit()
    {
        putenv('USE_NEW_COMMIT_MSG=1');
        $this->runtestExpectedOutput();
        self::assertEquals($this->hasCorrectCommit, true);
        $parts = explode(Helpers::getCommitMessageSeparator(), $this->commitCommand);
        $data = Yaml::parse($parts[1]);
        self::assertNotEmpty($data["update_data"]);
        self::assertEquals($data["update_data"]["from"], '1.0.0');
        self::assertEquals($data["update_data"]["to"], '1.0.2');
        self::assertEquals($data["update_data"]["package"], 'psr/log');
    }

    protected function handleExecutorReturnCallback($cmd, &$return)
    {
        $cmd_string = implode(' ', $cmd);
        if (strpos($cmd_string, $this->getCorrectCommit()) !== false) {
            $this->hasCorrectCommit = true;
            $this->commitCommand = $cmd_string;
        }
    }

    protected function getCorrectCommit()
    {
        return 'git commit composer.json composer.lock -m Update psr/log';
    }
}
