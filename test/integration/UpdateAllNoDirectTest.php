<?php

namespace eiriksm\CosyComposerTest\integration;

class UpdateAllNoDirectTest extends UpdateAllBase
{

    protected $composerJson = 'composer.allow_all.indirect.json';
    protected $composerLock = 'composer.allow_all.indirect.lock';

    public function testUpdate()
    {
        $this->getMockOutputWithUpdate('symfony/polyfill-mbstring', 'v1.23.1', 'v1.24.0');
        $this->cosy->run();
        self::assertEquals($this->foundCommand, true);
    }
}
