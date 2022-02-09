<?php

namespace eiriksm\CosyComposerTest\integration;

class UpdateAllBranchPrefixTest extends UpdateAllBase
{

    protected $composerJson = 'composer.allow_all_branch_prefix.json';
    protected $branchName = 'my_prefixviolinistall';

    public function testUpdateAllPlain()
    {
        $this->cosy->run();
        self::assertEquals($this->foundBranch, true);
    }
}
