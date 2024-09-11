<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\SecurityChecker\SecurityCheckerInterface;

class UpdateAllSecurityOnlyTest extends UpdateAllBase
{

    protected $composerJson = 'composer.allow_all_security_updates_only.json';

    public function testUpdateAllButNoSecRelease()
    {
        $this->cosy->run();
        self::assertEquals($this->foundCommand, false);
    }

    public function testUpdateallAndSecRelease()
    {
        $checker = $this->createMock(SecurityCheckerInterface::class);
        $checker->method('checkDirectory')
            ->willReturn([
                'psr/log' => true,
            ]);
        $this->cosy->getCheckerFactory()->setChecker($checker);
        $this->cosy->run();
        self::assertTrue($this->foundCommand);
        self::assertTrue($this->foundBranch);
        self::assertTrue($this->foundCommit);
    }
}
