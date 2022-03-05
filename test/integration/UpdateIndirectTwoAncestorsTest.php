<?php

namespace eiriksm\CosyComposerTest\integration;

class UpdateIndirectTwoAncestorsTest extends ComposerUpdateIntegrationBase
{
    protected $packageForUpdateOutput = 'symfony/polyfill-mbstring';
    protected $packageVersionForFromUpdateOutput = '1.1.1';
    protected $packageVersionForToUpdateOutput = '1.1.2';
    protected $composerAssetFiles = 'composer.indirect.multi_ancestors';
    protected $usesDirect = false;
    protected $checkPrUrl = true;

    public function testUpdateIndirectSeveral()
    {
        $this->runtestExpectedOutput();
        $output = $this->cosy->getOutput();
        $branch_pr_messages = [
            'Creating pull request from psypsyshv0112dependencies',
            'Creating pull request from symfonyvardumperv545dependencies',
        ];
        foreach ($output as $message) {
            if (!in_array($message->getMessage(), $branch_pr_messages)) {
                continue;
            }
            $col = array_search($message->getMessage(), $branch_pr_messages);
            unset($branch_pr_messages[$col]);
        }
        self::assertCount(0, $branch_pr_messages, 'All expected messages was not found in the output');
    }

    protected function createExpectedCommandForPackage($package)
    {
        // We are actually updating the required package which depends on this one.
        return 'composer update -n --no-ansi psy/psysh --with-dependencies ';
    }
}
