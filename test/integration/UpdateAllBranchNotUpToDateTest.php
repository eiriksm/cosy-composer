<?php

namespace eiriksm\CosyComposerTest\integration;

class UpdateAllBranchNotUpToDateTest extends UpdateAllBase
{

    protected $changeTitle = false;
    protected $changeBody = false;
    protected $changeSha = false;

    public function testUpdateAllNotUpToDateTitle()
    {
        $this->changeTitle = true;
        $this->runTheExpectedTest();
    }

    public function testUpdateAllNotUpToDateBody()
    {
        $this->changeBody = true;
        $this->runTheExpectedTest();
    }

    public function testUpdateAllNotUpToDateSha()
    {
        $this->changeSha = true;
        $this->runTheExpectedTest();
    }

    public function testUpdateAllNotUpToDateShaTitleAndBody()
    {
        $this->changeBody = true;
        $this->changeSha = true;
        $this->changeTitle = true;
        $this->runTheExpectedTest();
    }

    protected function runTheExpectedTest()
    {
        $this->cosy->run();
        // We tried to update to see what happened. For sure.
        self::assertEquals($this->foundCommand, true);
        // We did commit, since the branch required to be updated.
        self::assertEquals($this->foundCommit, true);
        self::assertEquals($this->foundBranch, true);
    }

    protected function getPrsNamed()
    {
        $title = 'Update all composer dependencies';
        $body = 'If you have a high test coverage index, and your tests for this pull request are passing, it should be both safe and recommended to merge this update.

### Updated packages

Some times an update also needs new or updated dependencies to be installed. Even if this branch is for updating one dependency, it might contain other installs or updates. All of the updates in this branch can be found here.

<details>
<summary>List of updated packages</summary>

- psr/log: 1.1.4 (updated from 1.0.0)

</details>


***
This is an automated pull request from [Violinist](https://violinist.io/): Continuously and automatically monitor and update your composer dependencies. Have ideas on how to improve this message? All violinist messages are open-source, and [can be improved here](https://github.com/violinist-dev/violinist-messages).
';
        if ($this->changeTitle) {
            // Garble it randomly.
            $title = md5($title . time());
        }
        if ($this->changeBody) {
            $body = md5($body . time());
        }
        // This sha is what the base class will return.
        $sha = 123;
        if ($this->changeSha) {
            $sha = md5($sha . time());
        }
        return [
            'violinistall' => [
                'base' => [
                    'sha' => $sha,
                ],
                'title' => $title,
                'body' => $body,
            ],
        ];
    }
}
