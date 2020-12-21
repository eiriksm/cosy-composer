<?php

namespace eiriksm\CosyComposerTest\integration;

class PackagesRemovedInUpdatesTest extends ComposerUpdateIntegrationBase
{

    protected $packageForUpdateOutput = 'drush/drush';
    protected $packageVersionForFromUpdateOutput = '9.7.2';
    protected $packageVersionForToUpdateOutput = '10.3.6';
    protected $composerAssetFiles = 'composer192';
    protected $checkPrUrl = true;

    public function testRemovalsInPackagesUpdated()
    {
        $this->runtestExpectedOutput();
        $this->assertEquals('If you have a high test coverage index, and your tests for this pull request are passing, it should be both safe and recommended to merge this update.

### Updated packages

Some times an update also needs new or updated dependencies to be installed. Even if this branch is for updating one dependency, it might contain other installs or updates. All of the updates in this branch can be found here.

<details>
<summary>List of updated packages</summary>

- symfony/debug v4.4.18 (package was removed)
- symfony/polyfill-php72 v1.20.0 (package was removed)
- chi-teck/drupal-code-generator: 1.33.1 (updated from 1.32.1)
- composer/semver: 3.2.4 (updated from 1.7.2)
- consolidation/annotated-command: 4.2.4 (updated from 2.12.1)
- consolidation/log: 2.0.2 (updated from 1.1.1)
- consolidation/output-formatters: 4.1.2 (updated from 3.5.1)
- consolidation/robo: 2.2.2 (updated from 1.4.13)
- consolidation/site-process: 4.0.0 (updated from 2.1.0)
- drush/drush: 10.3.6 (updated from 9.7.2)
- guzzlehttp/guzzle: 7.2.0 (new package, previously not installed)
- guzzlehttp/promises: 1.4.0 (new package, previously not installed)
- guzzlehttp/psr7: 1.7.0 (new package, previously not installed)
- psr/http-client: 1.0.1 (new package, previously not installed)
- psr/http-message: 1.0.1 (new package, previously not installed)
- ralouphie/getallheaders: 3.0.3 (new package, previously not installed)
- symfony/console: v4.4.18 (updated from v3.4.47)
- symfony/finder: v5.2.1 (updated from v4.4.18)
- symfony/polyfill-php73: v1.20.0 (new package, previously not installed)
- symfony/process: v4.4.18 (updated from v3.4.47)
- symfony/service-contracts: v2.2.0 (new package, previously not installed)
- symfony/var-dumper: v5.2.1 (updated from v4.4.18)
- symfony/yaml: v4.4.18 (updated from v3.4.47)

</details>


***
This is an automated pull request from [Violinist](https://violinist.io/): Continuously and automatically monitor and update your composer dependencies. Have ideas on how to improve this message? All violinist messages are open-source, and [can be improved here](https://github.com/violinist-dev/violinist-messages).
', $this->prParams["body"]);
    }
}
