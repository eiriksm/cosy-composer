<?php

namespace eiriksm\CosyComposerTest\integration;

class GroupsForDrupalCoreAndContribTest extends ComposerUpdateIntegrationBase
{
    private $foundContribMessage = false;
    private $foundCoreMessage = false;
    private $stdout = '';
    private $errorOutput = '';
    protected $composerAssetFiles = 'composer-group-contrib-and-core';
    protected $updateJson = '{
    "installed": [
        {
            "name": "drupal/coffee",
            "direct-dependency": true,
            "homepage": "https://drupal.org/project/coffee",
            "source": "https://git.drupalcode.org/project/coffee",
            "version": "2.0.0",
            "latest": "2.0.1",
            "latest-status": "semver-safe-update",
            "description": "Provides an Alfred like search box to navigate within your site.",
            "abandoned": false
        },
        {
            "name": "drupal/core-composer-scaffold",
            "direct-dependency": true,
            "homepage": "https://www.drupal.org/project/drupal",
            "source": "https://github.com/drupal/core-composer-scaffold/tree/11.1.4",
            "version": "11.1.4",
            "latest": "11.1.5",
            "latest-status": "semver-safe-update",
            "description": "A flexible Composer project scaffold builder.",
            "abandoned": false
        },
        {
            "name": "drupal/core-project-message",
            "direct-dependency": true,
            "homepage": "https://www.drupal.org/project/drupal",
            "source": "https://github.com/drupal/core-project-message/tree/11.1.4",
            "version": "11.1.4",
            "latest": "11.1.5",
            "latest-status": "semver-safe-update",
            "description": "Adds a message after Composer installation.",
            "abandoned": false
        },
        {
            "name": "drupal/core-recommended",
            "direct-dependency": true,
            "homepage": null,
            "source": "https://github.com/drupal/core-recommended/tree/11.1.4",
            "version": "11.1.4",
            "latest": "11.1.5",
            "latest-status": "semver-safe-update",
            "description": "Core and its dependencies with known-compatible minor versions. Require this project INSTEAD OF drupal/core.",
            "abandoned": false
        },
        {
            "name": "drupal/gin",
            "direct-dependency": true,
            "homepage": "https://www.drupal.org/project/gin",
            "source": "https://git.drupalcode.org/project/gin",
            "version": "4.0.5",
            "latest": "4.0.6",
            "latest-status": "semver-safe-update",
            "description": "For a better Admin and Content Editor Experience.",
            "abandoned": false
        }
    ]
}
';

    public function testUpdatesGroupedContribAndCore()
    {
        $this->runtestExpectedOutput();
        $output = $this->cosy->getOutput();
        self::assertEquals($this->prParamsArray[0]["head"], 'minor-and-patch-contrib');
        self::assertEquals(trim('This pull request updates the packages inside `Minor and Patch Contrib` to the latest version available (and inside your package constraint). The packages updated are listed below, along with available information for them.

If you have a high test coverage index, and your tests for this pull request are passing, it should be both safe and recommended to merge this update.

## Summary

| Package | Current version | New version |
| ------- | --------------- | ----------- |
| drupal/coffee | `2.0.0` | `2.0.1` |
| drupal/gin | `4.0.5` | `4.0.6` |

## drupal/coffee (2.0.0 → 2.0.1)

### Release notes

Here are the release notes for all versions released between your current running version, and the version this PR updates the package to.

<details>
    <summary>List of release notes</summary>

- [Release notes for tag 2.0.1](https://www.drupal.org/project/coffee/releases/2.0.1)

</details>


### Changed files

Here is a list of changed files between the version you use, and the version this pull request updates to:

<details>
    <summary>List of changed files</summary>

        README.md
        README.txt
        js/coffee.js
        logo.png
    
</details>


### Changelog

Here is a list of changes between the version you use, and the version this pull request updates to:

- [be87eba](https://git.drupalcode.org/project/coffee/commit/be87eba) `(HEAD -&gt; 2.x, tag: 2.0.1, origin/HEAD, origin/2.x) Issue #3320438 by _pratik_, nebel54, Nila Hyalij, shubham rathore, nitapawar: Replace README.txt with README.md`
- [6dd1139](https://git.drupalcode.org/project/coffee/commit/6dd1139) `Issue #3406045: Add logo for Project Browser`
- [81d771b](https://git.drupalcode.org/project/coffee/commit/81d771b) `Issue #3494208 by catch, kumareshbaksi: Get coffee data only when the search box is opened`



## drupal/gin (4.0.5 → 4.0.6)


### Changed files

Here is a list of changed files between the version you use, and the version this pull request updates to:

<details>
    <summary>List of changed files</summary>

        dist/css/deprecated/project_browser.css
        gin.info.yml
        gin.libraries.yml
        includes/helper.theme
        includes/modules.theme
        styles/deprecated/project_browser.scss
        webpack.config.js
    
</details>


### Changelog

Here is a list of changes between the version you use, and the version this pull request updates to:

- [1d4c1697](https://git.drupalcode.org/project/gin/commit/1d4c1697) `(HEAD -&gt; 4.0.x, tag: 4.0.6, origin/HEAD, origin/4.0.x) Resolve #3508067 &quot;Check for pb version&quot;`




### Working with this branch

If you find you need to update the codebase to be able to merge this branch (for example update some tests or rebuild some assets), please note that violinist will force push to this branch to keep it up to date. This means you should not work on this branch directly, since you might lose your work. [Read more about branches created by violinist.io here](https://docs.violinist.io/introduction/branches/).

***
This is an automated pull request from [Violinist](https://violinist.io/): Continuously and automatically monitor and update your composer dependencies. Have ideas on how to improve this message? All violinist messages are open-source, and [can be improved here](https://github.com/violinist-dev/violinist-messages).

'), trim($this->prParamsArray[0]["body"]));
        self::assertEquals($this->prParamsArray[0]["title"], 'Update group `Minor and Patch Contrib`');
        self::assertEquals($this->prParamsArray[1]["head"], 'minor-patch-core');
        self::assertTrue($this->foundCoreMessage);
        self::assertTrue($this->foundContribMessage);
    }

    public function handleExecutorReturnCallback(array $cmd, &$return)
    {
        $this->stdout = '';
        $this->errorOutput = '';
        // If it contains all of these items it seems to be the contrib update
        // command.
        $command_parts = ['composer', 'update', 'drupal/coffee', 'drupal/gin'];
        if (count(array_intersect($command_parts, $cmd)) === count($command_parts)) {
            $this->placeComposerLockContentsFromFixture('composer-group-contrib-and-core.lock.updated_contrib', $this->dir);
        }
        // If its the core thing, let's do that.
        $command_parts_for_core = ['composer', 'update', 'drupal/core-project-message', 'drupal/core-recommended'];
        if (count(array_intersect($command_parts_for_core, $cmd)) === count($command_parts_for_core)) {
            $this->placeComposerLockContentsFromFixture('composer-group-contrib-and-core.lock.updated_core', $this->dir);
        }
        // The last part might be the commit message for the contrib update.
        if (in_array('Update dependency group Minor and Patch Contrib', $cmd, true)) {
            $this->foundContribMessage = true;
        }
        if (in_array('Update dependency group Minor and Patch Core', $cmd, true)) {
            $this->foundCoreMessage = true;
        }
        $cmd_string = implode(' ', $cmd);
        if ($cmd_string === 'git -C /tmp/1d5bf652d7764ca52c520543a832c577 log 2.0.0..2.0.1 --oneline') {
            $this->stdout = 'be87eba (HEAD -> 2.x, tag: 2.0.1, origin/HEAD, origin/2.x) Issue #3320438 by _pratik_, nebel54, Nila Hyalij, shubham rathore, nitapawar: Replace README.txt with README.md
6dd1139 Issue #3406045: Add logo for Project Browser
81d771b Issue #3494208 by catch, kumareshbaksi: Get coffee data only when the search box is opened
';
        }
        if ($cmd_string === 'git -C /tmp/1d5bf652d7764ca52c520543a832c577 diff --name-only 2.0.0 2.0.1') {
            $this->stdout = 'README.md
README.txt
js/coffee.js
logo.png
';
        }
        if ($cmd_string === 'git -C /tmp/1d5bf652d7764ca52c520543a832c577 log 2.0.0...2.0.1 --decorate --simplify-by-decoration') {
            $this->stdout = 'commit be87eba8028697cacc196a5c70e186217418b076 (HEAD -> 2.x, tag: 2.0.1, origin/HEAD, origin/2.x)
Author: Pratik <60327-_pratik_@users.noreply.drupalcode.org>
Date:   Tue Jan 7 10:34:40 2025 +0000

    Issue #3320438 by _pratik_, nebel54, Nila Hyalij, shubham rathore, nitapawar: Replace README.txt with README.md
';
        }
        if ($cmd_string === 'git -C /tmp/42d5fa2a4368d89b13959a01656dd4be log 4.0.5..4.0.6 --oneline') {
            $this->stdout = '1d4c1697 (HEAD -> 4.0.x, tag: 4.0.6, origin/HEAD, origin/4.0.x) Resolve #3508067 "Check for pb version"
';
        }
        if ($cmd_string === 'git -C /tmp/42d5fa2a4368d89b13959a01656dd4be diff --name-only 4.0.5 4.0.6') {
            $this->stdout = 'dist/css/deprecated/project_browser.css
gin.info.yml
gin.libraries.yml
includes/helper.theme
includes/modules.theme
styles/deprecated/project_browser.scss
webpack.config.js
';
        }
        if ($cmd_string === 'git -C /tmp/42d5fa2a4368d89b13959a01656dd4be log 4.0.5...4.0.6 --decorate --simplify-by-decoration') {
            $this->stdout = 'Author: Sascha Eggenberger <46355-saschaeggi@users.noreply.drupalcode.org>
Date:   Fri Feb 28 10:04:55 2025 +0000

    Resolve #3508067 "Check for pb version"
';
        }
    }

    protected function processLastOutput(array &$output)
    {
        if (!empty($output['stdout'])) {
            $this->stdout = $output['stdout'];
        }
        if (!empty($output['stderr'])) {
            $this->errorOutput = $output['stderr'];
        }
        $output['stdout'] = $this->stdout;
        $output['stderr'] = $this->errorOutput;
    }
}
