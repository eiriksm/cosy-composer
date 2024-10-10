<?php

namespace eiriksm\CosyComposerTest\unit;

use eiriksm\CosyComposer\NativeComposerChecker;
use eiriksm\CosyComposer\SecurityCheckerFactory;
use PHPUnit\Framework\TestCase;
use Violinist\SymfonyCloudSecurityChecker\SecurityChecker;

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
        self::assertInstanceOf(SecurityChecker::class, $this->checkerFactory->getChecker());
    }

    public function testNoCheckerSet()
    {
        self::assertInstanceOf(NativeComposerChecker::class, $this->checkerFactory->getChecker());
    }
}
