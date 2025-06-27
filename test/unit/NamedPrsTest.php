<?php

namespace eiriksm\CosyComposerTest\unit;


use eiriksm\CosyComposer\Providers\NamedPrs;
use PHPUnit\Framework\TestCase;

class NamedPrsTest extends TestCase
{
    public function testFromCommit()
    {
        $commit_message = 'Update psr/log

------
update_data:
    package: psr/log
    from: 1.0.0
    to: 1.1.4';
        $named_prs = new NamedPrs();
        $named_prs->addFromCommit($commit_message, [
            'number' => 123,
        ]);
        $prs_retrieved = $named_prs->getPrsFromPackage('psr/log');
        self::assertEquals($prs_retrieved[0]['number'], 123);
    }
}
