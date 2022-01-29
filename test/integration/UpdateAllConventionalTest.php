<?php

namespace eiriksm\CosyComposerTest\integration;

use Violinist\Slug\Slug;
use Violinist\SymfonyCloudSecurityChecker\SecurityChecker;

class UpdateAllConventionalTest extends Base
{

    public function testUpdateAllPlain()
    {
        $this->createComposerFileFromFixtures($this->dir, 'composer.allow_all_conventional.json');
        $mock_output = $this->getMockOutputWithUpdate('psr/log', '1.0.0', '1.1.4');
        $this->placeComposerLockContentsFromFixture('composer.allow_all.lock', $this->dir);
        $this->cosy->setOutput($mock_output);
        $this->setDummyGithubProvider();
        $found_commit = false;
        $executor = $this->getMockExecuterWithReturnCallback(function ($command) use (&$found_commit) {
            // We are looking for the very blindly calling of composer update.
            if ($command === 'composer update') {
                // We also want to place the updated lock file there.
                $this->placeComposerLockContentsFromFixture('composer.allow_all.lock.updated', $this->dir);
            }
            if (mb_strpos($command, 'build(deps): Update all dependencies')) {
                $found_commit = true;
            }
        });
        $this->cosy->setExecuter($executor);
        $this->cosy->run();
        self::assertEquals($found_commit, true);
    }

}
