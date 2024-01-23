<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\ArrayOutput\ArrayOutput;
use eiriksm\CosyComposer\ProviderFactory;
use eiriksm\CosyComposer\Providers\Github;
use Violinist\Slug\Slug;

class ComposerUpdateUpdateTest extends ComposerUpdateIntegrationBase
{
    public function testUpdateAvailableButUpdatedToOther()
    {
        $this->packageForUpdateOutput = 'drupal/core';
        $this->packageVersionForFromUpdateOutput = '8.4.7';
        $this->packageVersionForToUpdateOutput = '8.5.4';
        $this->composerAssetFiles = 'composer-drupal-847';
        $expected_pr = [
            'base' => 'master',
            'head' => 'drupalcore847848',
            'title' => 'Update drupal/core from 8.4.7 to 8.4.8',
            'body' => 'If you have a high test coverage index, and your tests for this pull request are passing, it should be both safe and recommended to merge this update.

### Updated packages

Some times an update also needs new or updated dependencies to be installed. Even if this branch is for updating one dependency, it might contain other installs or updates. All of the updates in this branch can be found here:

- drupal/core: 8.4.8 (updated from 8.4.7)



***
This is an automated pull request from [Violinist](https://violinist.io/): Continuously and automatically monitor and update your composer dependencies. Have ideas on how to improve this message? All violinist messages are open-source, and [can be improved here](https://github.com/violinist-dev/violinist-messages).
',
            'assignees' => [],
        ];
        $this->checkPrUrl = true;
        $this->setUp();
        $this->runtestExpectedOutput();
        self::assertEquals($expected_pr, $this->prParams);
    }

    public function testUpdatedAndNewInstalled()
    {
        $this->packageForUpdateOutput = 'drupal/core';
        $this->packageVersionForFromUpdateOutput = '8.8.0';
        $this->packageVersionForToUpdateOutput = '8.9.3';
        $this->composerAssetFiles = 'composer-drupal88';
        $this->checkPrUrl = true;
        $this->setUp();
        $this->runtestExpectedOutput();
        self::assertEquals([
            'base' => 'master',
            'head' => 'drupalcore880893',
            'title' => 'Update drupal/core from 8.8.0 to 8.9.3',
            'body' => 'If you have a high test coverage index, and your tests for this pull request are passing, it should be both safe and recommended to merge this update.

### Updated packages

Some times an update also needs new or updated dependencies to be installed. Even if this branch is for updating one dependency, it might contain other installs or updates. All of the updates in this branch can be found here:

- zendframework/zend-diactoros 1.8.7 (package was removed)
- zendframework/zend-escaper 2.6.1 (package was removed)
- zendframework/zend-feed 2.12.0 (package was removed)
- zendframework/zend-stdlib 3.2.1 (package was removed)
- drupal/core: 8.9.3 (updated from 8.8.0)
- laminas/laminas-diactoros: 1.8.7p2 (new package, previously not installed)
- laminas/laminas-escaper: 2.6.1 (new package, previously not installed)
- laminas/laminas-feed: 2.12.3 (new package, previously not installed)
- laminas/laminas-stdlib: 3.3.0 (new package, previously not installed)
- laminas/laminas-zendframework-bridge: 1.1.0 (new package, previously not installed)



***
This is an automated pull request from [Violinist](https://violinist.io/): Continuously and automatically monitor and update your composer dependencies. Have ideas on how to improve this message? All violinist messages are open-source, and [can be improved here](https://github.com/violinist-dev/violinist-messages).
',
            'assignees' => [],
        ], $this->prParams);
    }
}
