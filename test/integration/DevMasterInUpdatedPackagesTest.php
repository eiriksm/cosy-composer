<?php

namespace eiriksm\CosyComposerTest\integration;

use Violinist\Slug\Slug;

class DevMasterInUpdatedPackagesTest extends ComposerUpdateIntegrationBase
{

    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = 'dev-master 2b71ffb';
    protected $packageVersionForToUpdateOutput = 'dev-master dd738d0';
    protected $composerAssetFiles = 'composer-dev-master';

    public function testUpdatesInPackagesUpdated()
    {
        $fake_pr_url = 'http://example.com/pr';
        $pr_params = [];
        $this->mockProvider->method('createPullRequest')
            ->willReturnCallback(function (Slug $slug, array $params) use (&$pr_params, $fake_pr_url) {
                $pr_params = $params;
                return [
                    'html_url' => $fake_pr_url
                ];
            });
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage($fake_pr_url, $this->cosy);
        $this->assertEquals('If you have a high test coverage index, and your tests for this pull request are passing, it should be both safe and recommended to merge this update.

### Updated packages

Some times an update also needs new or updated dependencies to be installed. Even if this branch is for updating one dependency, it might contain other installs or updates. All of the updates in this branch can be found here.

<details>
<summary>List of updated packages</summary>

- psr/log: dev-master#dd738d0b4491f32725492cf345f6b501f5922fec (updated from dev-master#2b71ffbefcc3a1ccb610294835bcfde8f594f8e7)

</details>


***
This is an automated pull request from [Violinist](https://violinist.io/): Continuously and automatically monitor and update your composer dependencies. Have ideas on how to improve this message? All violinist messages are open-source, and [can be improved here](https://github.com/violinist-dev/violinist-messages).
', $pr_params["body"]);
    }
}
