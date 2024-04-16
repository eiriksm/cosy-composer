<?php

namespace eiriksm\CosyComposerTest\integration;

use Violinist\Slug\Slug;
use Violinist\SymfonyCloudSecurityChecker\SecurityChecker;

class UpdateAllBranchUpToDateTest extends UpdateAllBase
{

    public function testUpdateAllUpToDate()
    {
        $this->cosy->run();
        // We tried to update to see what happened. For sure.
        self::assertEquals($this->foundCommand, true);
        // We did not commit.
        self::assertEquals($this->foundCommit, false);
        // We did try to switch branch at some point there. But after we did, we found out what we ended up updating,
        // and that made us not commit, and certainly not push.
        self::assertEquals($this->foundBranch, true);
    }

    protected function getPrsNamed()
    {
        return [
            'violinistall' => [
                'base' => [
                    // The dummy API response will return 123 as the SHA.
                    'sha' => 123,
                ],
                'title' => 'Update all composer dependencies',
                'body' => 'If you have a high test coverage index, and your tests for this pull request are passing, it should be both safe and recommended to merge this update.

### Updated packages

Some times an update also needs new or updated dependencies to be installed. Even if this branch is for updating one dependency, it might contain other installs or updates. All of the updates in this branch can be found here:

- psr/log: 1.1.4 (updated from 1.0.0)



### Working with this branch

If you find you need to update the codebase to be able to merge this branch (for example update some tests or rebuild some assets), please note that violinist will force push to this branch to keep it up to date. This means you should not work on this branch directly, since you might lose your work. [Read more about branches created by violinist.io here](https://docs.violinist.io/#branches).

***
This is an automated pull request from [Violinist](https://violinist.io/): Continuously and automatically monitor and update your composer dependencies. Have ideas on how to improve this message? All violinist messages are open-source, and [can be improved here](https://github.com/violinist-dev/violinist-messages).
',
            ],
        ];
    }
}
