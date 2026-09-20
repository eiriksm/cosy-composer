<?php

namespace eiriksm\CosyComposerTest\integration;

class UpdateAllNoLockTest extends Base
{

    public function testUpdateAllPlainNoLock()
    {
        $this->createComposerFileFromFixtures($this->dir, 'composer.allow_all.json');
        $this->getMockOutputWithUpdate('psr/log', '1.0.0', '1.1.4');
        $this->setDummyGithubProvider();
        $found_command = false;
        $executor = $this->getMockExecuterWithReturnCallback(function ($command) use (&$found_command) {
            // We are looking for the very blindly calling of composer update.
            if ($command === ['composer', 'update']) {
                $found_command = true;
            }
            $this->lastCommand = $command;
        });
        $this->ensureMockExecuterProvidesLastOutput($executor);
        $this->cosy->setExecuter($executor);
        $this->cosy->run();
        self::assertEquals($found_command, false);
    }
}
