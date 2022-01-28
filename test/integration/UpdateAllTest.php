<?php

namespace eiriksm\CosyComposerTest\integration;

class UpdateAllTest extends Base
{

    public function testUpdateAllPlain()
    {
        $this->createComposerFileFromFixtures($this->dir, 'composer.allow_all.json');
        $mock_output = $this->getMockOutputWithUpdate('psr/log', '1.0.0', '1.1.4');
        $this->placeComposerLockContentsFromFixture('composer.allow_all.lock', $this->dir);
        $this->cosy->setOutput($mock_output);
        $this->setDummyGithubProvider();
        $found_command = false;
        $executor = $this->getMockExecuterWithReturnCallback(function ($command) use (&$found_command) {
            // We are looking for the very blindly calling of composer update.
            if ($command === 'composer update') {
                $found_command = true;
                // We also want to place the updated lock file there.
                $this->placeComposerLockContentsFromFixture('composer.allow_all.lock.updated', $this->dir);
            }
        });
        $this->cosy->setExecuter($executor);
        $this->cosy->run();
        self::assertEquals($found_command, true);
    }
}
