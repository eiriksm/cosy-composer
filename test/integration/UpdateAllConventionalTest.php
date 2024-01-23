<?php

namespace eiriksm\CosyComposerTest\integration;

use Violinist\Slug\Slug;
use Violinist\SymfonyCloudSecurityChecker\SecurityChecker;

class UpdateAllConventionalTest extends UpdateAllBase
{

    protected $composerJson = 'composer.allow_all_conventional.json';

    public function testUpdateAllPlain()
    {
        $executor = $this->getMockExecuterWithReturnCallback(function ($command) {
            // We are looking for the very blindly calling of composer update.
            if ($command === ['composer', 'update']) {
                // We also want to place the updated lock file there.
                $this->placeComposerLockContentsFromFixture('composer.allow_all.lock.updated', $this->dir);
            }
            $cmd = implode(' ', $command);
            if (mb_strpos($cmd, 'build(deps): Update all dependencies')) {
                $this->foundCommit = true;
            }
            $this->lastCommand = $command;
        });
        $this->ensureMockExecuterProvidesLastOutput($executor);
        $this->cosy->setExecuter($executor);
        $this->cosy->run();
        self::assertEquals($this->foundCommit, true);
    }
}
