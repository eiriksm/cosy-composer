<?php

namespace eiriksm\CosyComposerTest\unit;

use eiriksm\CosyComposer\ComposerFileGetter;
use League\Flysystem\FilesystemAdapter;
use PHPUnit\Framework\TestCase;

class ComposerFileGetterTest extends TestCase
{
    public function testHasComposerFile()
    {
        $adapter = $this->createMock(FilesystemAdapter::class);
        $adapter->expects($this->once())
            ->method('fileExists')
            ->with('composer.json')
            ->willReturn(false);
        $getter = new ComposerFileGetter($adapter);
        $this->assertEquals(false, $getter->hasComposerFile());
    }

    public function testBadJsonData()
    {
        $adapter = $this->createMock(FilesystemAdapter::class);
        $adapter->expects($this->once())
            ->method('fileExists')
            ->with('composer.json')
            ->willReturn(true);
        $adapter->expects($this->once())
            ->method('read')
            ->with('composer.json')
            ->willReturn(false);
        $getter = new ComposerFileGetter($adapter);
        $this->assertEquals(false, $getter->getComposerJsonData());
    }

    public function testReadComposerJsonContents()
    {
        $adapter = $this->createMock(FilesystemAdapter::class);
        $adapter->expects($this->once())
            ->method('fileExists')
            ->with('composer.json')
            ->willReturn(true);
        $adapter->expects($this->once())
            ->method('read')
            ->with('composer.json')
            ->willReturn(['contents' => '{"data": "yes"}']);
        $getter = new ComposerFileGetter($adapter);
        $this->assertEquals((object) ['data' => 'yes'], $getter->getComposerJsonData());
    }
}
