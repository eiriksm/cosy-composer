<?php

namespace eiriksm\CosyComposerTest\unit;

use eiriksm\CosyComposer\Message;
use PHPUnit\Framework\TestCase;

class MessageTest extends TestCase
{
    public function testMethods()
    {
        $time = time();
        $msg = new Message('test', Message::PR_URL);
        $this->assertEquals('test', $msg->getMessage());
        $this->assertEquals(Message::PR_URL, $msg->getType());
        $this->assertEquals(true, ($time <= $msg->getTimestamp()));
    }
}
