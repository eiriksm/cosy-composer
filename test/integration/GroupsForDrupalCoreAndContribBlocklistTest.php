<?php

namespace eiriksm\CosyComposerTest\integration;

class GroupsForDrupalCoreAndContribBlocklistTest extends ComposerUpdateIntegrationBase
{
    private $stdout = '';
    private $errorOutput = '';

    /**
     * @var array<int, array<int, string>>
     */
    private $composerUpdateCommands = [];

    protected $composerAssetFiles = 'composer-group-contrib-and-core-blocklist';

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

    public function testCoreBundledWithBlocklist()
    {
        $this->runtestExpectedOutput();
        $messages = array_map(static function ($message) {
            return $message->getMessage();
        }, $this->cosy->getOutput());

        foreach ([
            'Skipping update of drupal/core-composer-scaffold because it is on the block list',
            'Skipping update of drupal/core-project-message because it is on the block list',
        ] as $expected) {
            self::assertContains($expected, $messages);
        }

        self::assertCount(2, $this->prParamsArray);
        $corePr = null;
        foreach ($this->prParamsArray as $params) {
            if (($params['head'] ?? '') === 'minor-patch-core') {
                $corePr = $params;
                break;
            }
        }
        self::assertNotNull($corePr, 'Expected a PR for the Drupal core group');

        $coreCommands = array_filter($this->composerUpdateCommands, static function (array $command): bool {
            return in_array('drupal/core-recommended', $command, true);
        });
        self::assertCount(1, $coreCommands, 'Expected a single composer update command for Drupal core');
        $coreCommand = reset($coreCommands);
        foreach ([
            'drupal/core-recommended',
            'drupal/core-composer-scaffold',
            'drupal/core-project-message',
        ] as $package) {
            self::assertContains($package, $coreCommand);
        }
    }

    public function handleExecutorReturnCallback(array $cmd, &$return)
    {
        $this->stdout = '';
        $this->errorOutput = '';
        if (isset($cmd[0], $cmd[1]) && $cmd[0] === 'composer' && $cmd[1] === 'update') {
            $this->composerUpdateCommands[] = $cmd;
        }
        $command_parts = ['composer', 'update', 'drupal/coffee', 'drupal/gin'];
        if (count(array_intersect($command_parts, $cmd)) === count($command_parts)) {
            $this->placeComposerLockContentsFromFixture('composer-group-contrib-and-core-blocklist.lock.updated_contrib', $this->dir);
        }
        $command_parts_for_core = ['composer', 'update', 'drupal/core-composer-scaffold', 'drupal/core-project-message', 'drupal/core-recommended'];
        if (count(array_intersect($command_parts_for_core, $cmd)) === count($command_parts_for_core)) {
            $this->placeComposerLockContentsFromFixture('composer-group-contrib-and-core-blocklist.lock.updated_core', $this->dir);
        }
        $last_item = $cmd[count($cmd) - 1] ?? null;
        $cmd_string = implode(' ', $cmd);
        if ($cmd_string === 'git -C /tmp/1d5bf652d7764ca52c520543a832c577 log 2.0.0..2.0.1 --oneline') {
            $this->stdout = 'be87eba (HEAD -> 2.x, tag: 2.0.1, origin/HEAD, origin/2.x) Issue #3320438 by _pratik_, nebel54, Nila Hyalij, shubham rathore, nitapawar: Replace README.txt with README.md\n6dd1139 Issue #3406045: Add logo for Project Browser\n81d771b Issue #3494208 by catch, kumareshbaksi: Get coffee data only when the search box is opened\n';
        }
        if ($cmd_string === 'git -C /tmp/1d5bf652d7764ca52c520543a832c577 diff --name-only 2.0.0 2.0.1') {
            $this->stdout = 'README.md\nREADME.txt\njs/coffee.js\nlogo.png\n';
        }
        if ($cmd_string === 'git -C /tmp/1d5bf652d7764ca52c520543a832c577 log 2.0.0...2.0.1 --decorate --simplify-by-decoration') {
            $this->stdout = 'commit be87eba8028697cacc196a5c70e186217418b076 (HEAD -> 2.x, tag: 2.0.1, origin/HEAD, origin/2.x)\nAuthor: Pratik <60327-_pratik_@users.noreply.drupalcode.org>\nDate:   Tue Jan 7 10:34:40 2025 +0000\n\n    Issue #3320438 by _pratik_, nebel54, Nila Hyalij, shubham rathore, nitapawar: Replace README.txt with README.md\n';
        }
        if ($cmd_string === 'git -C /tmp/42d5fa2a4368d89b13959a01656dd4be log 4.0.5..4.0.6 --oneline') {
            $this->stdout = '1d4c1697 (HEAD -> 4.0.x, tag: 4.0.6, origin/HEAD, origin/4.0.x) Resolve #3508067 "Check for pb version"\n';
        }
        if ($cmd_string === 'git -C /tmp/42d5fa2a4368d89b13959a01656dd4be diff --name-only 4.0.5 4.0.6') {
            $this->stdout = 'dist/css/deprecated/project_browser.css\ngin.info.yml\ngin.libraries.yml\nincludes/helper.theme\nincludes/modules.theme\nstyles/deprecated/project_browser.scss\nwebpack.config.js\n';
        }
        if ($cmd_string === 'git -C /tmp/42d5fa2a4368d89b13959a01656dd4be log 4.0.5...4.0.6 --decorate --simplify-by-decoration') {
            $this->stdout = 'Author: Sascha Eggenberger <46355-saschaeggi@users.noreply.drupalcode.org>\nDate:   Fri Feb 28 10:04:55 2025 +0000\n\n    Resolve #3508067 "Check for pb version"\n';
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
