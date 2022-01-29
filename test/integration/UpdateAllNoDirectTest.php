<?php

namespace eiriksm\CosyComposerTest\integration;

use Composer\Console\Application;

class UpdateAllNoDirectTest extends UpdateAllBase
{

    protected $composerJson = 'composer.allow_all.indirect.json';
    protected $composerLock = 'composer.allow_all.indirect.lock';

    public function testUpdate()
    {
        $mock_output = $this->getMockOutputWithUpdate('symfony/polyfill-mbstring', 'v1.23.1', 'v1.24.0');
        $this->cosy->setOutput($mock_output);
        $this->cosy->run();
        self::assertEquals($this->foundCommand, true);
    }
}
