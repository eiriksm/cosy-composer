<?php

namespace eiriksm\CosyComposerTest\integration;

use GuzzleHttp\Psr7\Response;
use Http\Client\HttpClient;

class UpdateBranchTitleChangedTest extends ComposerUpdateIntegrationBase
{

    protected $composerAssetFiles = 'composer.drupal1021';
    protected $packageForUpdateOutput = 'drupal/core-recommended';
    protected $packageVersionForFromUpdateOutput = '10.2.1';
    protected $packageVersionForToUpdateOutput = '10.2.2';

    public function setUp() : void
    {
        parent::setUp();
        $mock_response = new Response(200, [], file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'updates-01-17-2024.xml'));
        $client = $this->createMock(HttpClient::class);
        $client->method('sendRequest')
            ->willReturn($mock_response);
        $this->cosy->setHttpClient($client);
    }

    public function testSecUpdateUpdatesBranch()
    {
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage('Updating the PR of drupal/core-recommended since the computed title does not match the title.', $this->cosy);
    }

    protected function getPrsNamed()
    {
        $title = 'Update drupal/core-recommended from 10.2.1 to 10.2.2';
        $body = 'If you have a high test coverage index, and your tests for this pull request are passing, it should be both safe and recommended to merge this update.

### Updated packages

Some times an update also needs new or updated dependencies to be installed. Even if this branch is for updating one dependency, it might contain other installs or updates. All of the updates in this branch can be found here:

- drupal/core: 10.2.2 (updated from 10.2.1)
- drupal/core-composer-scaffold: 10.2.2 (updated from 10.2.1)
- drupal/core-recommended: 10.2.2 (updated from 10.2.1)

### Release notes

Here are the release notes for all versions released between your current running version, and the version this PR updates the package to.

<details>
  <summary>List of release notes</summary>

- [Release notes for tag 10.2.2](https://github.com/drupal/core-recommended/releases/tag/10.2.2)

</details>

### Changed files

Here is a list of changed files between the version you use, and the version this pull request updates to:

<details>
  <summary>List of changed files</summary>

      composer.json
  </details>

### Changelog

Here is a list of changes between the version you use, and the version this pull request updates to:

- [d8cb769](https://github.com/drupal/core-recommended/commit/d8cb769) `Drupal 10.2.2`


***
This is an automated pull request from [Violinist](https://violinist.io/): Continuously and automatically monitor and update your composer dependencies. Have ideas on how to improve this message? All violinist messages are open-source, and [can be improved here](https://github.com/violinist-dev/violinist-messages).
';
        return [
            'drupalcorerecommended10211022' => [
                'base' => [
                    'sha' => 123,
                ],
                'number' => 342,
                'title' => $title,
                'body' => $body,
            ],
        ];
    }

    public function getBranchesFlattened()
    {
        return [
            'drupalcorerecommended10211022',
        ];
    }
}
