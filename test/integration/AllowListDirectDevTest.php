<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test for a default commit message.
 */
class AllowListDirectDevTest extends ComposerUpdateIntegrationBase
{
    protected $composerAssetFiles = 'composer.always_allow_direct_dependencies2';
    protected $hasUpdatedConsole = false;
    protected $hasUpdatedString = false;
    protected $usesDirect = false;
    protected $packageForUpdateOutput = 'psr/cache';

    public function testAllowList()
    {
        $this->runtestExpectedOutput();
        self::assertEquals($this->hasUpdatedConsole, true);
        self::assertEquals($this->hasUpdatedString, false);
    }

    protected function createUpdateJsonFromData($package, $version, $new_version)
    {
        return '{
    "installed": [
        {
            "name": "symfony/console",
            "direct-dependency": true,
            "homepage": "https://symfony.com",
            "source": "https://github.com/symfony/console/tree/v5.4.19",
            "version": "v5.4.19",
            "latest": "v5.4.21",
            "latest-status": "semver-safe-update",
            "description": "Eases the creation of beautiful and testable command line interfaces",
            "abandoned": false
        },
        {
            "name": "symfony/string",
            "direct-dependency": false,
            "homepage": "https://symfony.com",
            "source": "https://github.com/symfony/string/tree/v5.4.19",
            "version": "v5.4.19",
            "latest": "v5.4.21",
            "latest-status": "semver-safe-update",
            "description": "Provides an object-oriented API to strings and deals with bytes, UTF-8 code points and grapheme clusters in a unified way",
            "abandoned": false
        }
    ]
}
';
    }

    protected function handleExecutorReturnCallback($cmd, &$return)
    {
        $cmd_string = implode(' ', $cmd);
        if (strpos($cmd_string, 'symfony/console') !== false) {
            $this->hasUpdatedConsole = true;
        }
        if (strpos($cmd_string, 'symfony/string') !== false) {
            $this->hasUpdatedString = true;
        }
    }
}
