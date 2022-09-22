<?php

namespace eiriksm\CosyComposerTest\integration;

use Github\Exception\ValidationFailedException;
use Violinist\Slug\Slug;

/**
 * Test for branch prefix with one_per option set.
 *
 * Plus if the dependency was updated to something else than we expect it. Then let's use the same expected branch then
 * as well.
 */
class BranchPrefixOnePerUnexpectedUpdateButdoesNotNeedUpdateTest extends ComposerUpdateIntegrationBase
{
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.1.3';
    protected $composerAssetFiles = 'composerbranch.one_per';

    public function testBranchPrefixUsedAndOnePer()
    {
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage('Skipping psr/log because a pull request already exists', $this->cosy);
    }

    protected function createPullRequest(Slug $slug, array $params)
    {
        throw new ValidationFailedException();
    }

    protected function getPrsNamed()
    {
        return [
            'my_prefixviolinistpsrlog' => [
                'base' => [
                    'sha' => 123,
                ],
                'title' => 'Update psr/log from 1.0.0 to 1.1.4',
                'body' => 'If you have a high test coverage index, and your tests for this pull request are passing, it should be both safe and recommended to merge this update.

### Updated packages

Some times an update also needs new or updated dependencies to be installed. Even if this branch is for updating one dependency, it might contain other installs or updates. All of the updates in this branch can be found here.

<details>
<summary>List of updated packages</summary>

- psr/log: 1.1.4 (updated from 1.0.0)

</details>


***
This is an automated pull request from [Violinist](https://violinist.io/): Continuously and automatically monitor and update your composer dependencies. Have ideas on how to improve this message? All violinist messages are open-source, and [can be improved here](https://github.com/violinist-dev/violinist-messages).
',
            ]
        ];
    }
}
