<?php

namespace eiriksm\CosyComposerTest\unit;

use eiriksm\CosyComposer\SecurityChecker\NativeComposerChecker;
use eiriksm\CosyComposer\SecurityChecker\SecurityCheckerInterface;
use eiriksm\CosyComposer\SecurityCheckerFactory;
use PHPUnit\Framework\TestCase;

class SecurityCheckerFactoryTest extends TestCase
{

    /**
     * @var SecurityCheckerFactory
     */
    private $checkerFactory;

   /**
    * {@inheritdoc}
    */
    public function setUp(): void
    {
        parent::setUp();
        $this->checkerFactory = new SecurityCheckerFactory();
    }

    /**
     * Make sure we can get and set the checker as we want.
    */
    public function testCheckerSet()
    {
        $checker = $this->createMock(NativeComposerChecker::class);
        $this->checkerFactory->setChecker($checker);
        self::assertInstanceOf(SecurityCheckerInterface::class, $this->checkerFactory->getChecker());
    }

    public function testNoCheckerSet()
    {
        self::assertInstanceOf(NativeComposerChecker::class, $this->checkerFactory->getChecker());
    }
}
