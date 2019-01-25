<?php

namespace eiriksm\CosyComposerTest\unit;

use eiriksm\CosyComposer\ProcessFactory;
use PHPUnit\Framework\TestCase;

class ProcessFactoryTest extends TestCase
{
    public function testGetProcess()
    {
        $p = new ProcessFactory();
        $cwd = getcwd();
        $proc = $p->getProcess('echo');
        $this->assertEquals($cwd, $proc->getWorkingDirectory());
    }

    public function testCommandWithCwd()
    {
        $p = new ProcessFactory();
        $cwd = '/tmp/test';
        $proc = $p->getProcess('echo', $cwd);
        $this->assertEquals($cwd, $proc->getWorkingDirectory());
    }
}
