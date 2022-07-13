<?php

namespace eiriksm\CosyComposerTest\integration;

class UpdateIndirectActualProjectTest extends ComposerUpdateIntegrationBase
{
    protected $packageForUpdateOutput = 'composer/composer';
    protected $packageVersionForFromUpdateOutput = '2.2.16';
    protected $packageVersionForToUpdateOutput = '2.3.9';
    protected $composerAssetFiles = 'composer.actual_project';
    protected $usesDirect = false;

    protected function createUpdateJsonFromData($package, $version, $new_version)
    {
        return '{
    "installed": [
        {
            "name": "composer/composer",
            "version": "2.2.16",
            "latest": "2.3.9",
            "latest-status": "semver-safe-update",
            "description": "Composer helps you declare, manage and install dependencies of PHP projects. It ensures you have the right stack everywhere."
        },
        {
            "name": "doctrine/reflection",
            "version": "1.2.3",
            "latest": "1.2.3",
            "latest-status": "up-to-date",
            "description": "The Doctrine Reflection project is a simple library used by the various Doctrine projects which adds some additional functionality on top of the reflection functionality that comes with PHP. It allows you to get the reflection information about classes, methods and properties statically.",
            "warning": "Package doctrine/reflection is abandoned, you should avoid using it. Use roave/better-reflection instead."
        },
        {
            "name": "laminas/laminas-diactoros",
            "version": "2.11.3",
            "latest": "2.13.0",
            "latest-status": "semver-safe-update",
            "description": "PSR HTTP Message implementations"
        },
        {
            "name": "laminas/laminas-escaper",
            "version": "2.9.0",
            "latest": "2.10.0",
            "latest-status": "semver-safe-update",
            "description": "Securely and safely escape HTML, HTML attributes, JavaScript, CSS, and URLs"
        },
        {
            "name": "laminas/laminas-stdlib",
            "version": "3.7.1",
            "latest": "3.10.1",
            "latest-status": "semver-safe-update",
            "description": "SPL extensions, array utilities, error handlers, and more"
        },
        {
            "name": "symfony/debug",
            "version": "v4.4.41",
            "latest": "v4.4.41",
            "latest-status": "up-to-date",
            "description": "Provides tools to ease debugging PHP code",
            "warning": "Package symfony/debug is abandoned, you should avoid using it. Use symfony/error-handler instead."
        },
        {
            "name": "symfony/polyfill-ctype",
            "version": "v1.25.0",
            "latest": "v1.26.0",
            "latest-status": "semver-safe-update",
            "description": "Symfony polyfill for ctype functions"
        },
        {
            "name": "symfony/polyfill-iconv",
            "version": "v1.25.0",
            "latest": "v1.26.0",
            "latest-status": "semver-safe-update",
            "description": "Symfony polyfill for the Iconv extension"
        },
        {
            "name": "symfony/polyfill-intl-idn",
            "version": "v1.25.0",
            "latest": "v1.26.0",
            "latest-status": "semver-safe-update",
            "description": "Symfony polyfill for intls idn_to_ascii and idn_to_utf8 functions"
        },
        {
            "name": "symfony/polyfill-intl-normalizer",
            "version": "v1.25.0",
            "latest": "v1.26.0",
            "latest-status": "semver-safe-update",
            "description": "Symfony polyfill for intls Normalizer class and related functions"
        },
        {
            "name": "symfony/polyfill-mbstring",
            "version": "v1.25.0",
            "latest": "v1.26.0",
            "latest-status": "semver-safe-update",
            "description": "Symfony polyfill for the Mbstring extension"
        },
        {
            "name": "symfony/polyfill-php80",
            "version": "v1.25.0",
            "latest": "v1.26.0",
            "latest-status": "semver-safe-update",
            "description": "Symfony polyfill backporting some PHP 8.0+ features to lower PHP versions"
        },
        {
            "name": "webmozart/path-util",
            "version": "2.3.0",
            "latest": "2.3.0",
            "latest-status": "up-to-date",
            "description": "A robust cross-platform utility for normalizing, comparing and modifying file paths.",
            "warning": "Package webmozart/path-util is abandoned, you should avoid using it. Use symfony/filesystem instead."
        }
    ]
}
';
    }

    public function testUpdateIndirect()
    {
        $this->runtestExpectedOutput();
        $output = $this->cosy->getOutput();
        // We should have tried to update at least a couple of those, yeah?
        $total_found = 0;
        foreach (['symfony/polyfill-php80', 'composer/composer'] as $package) {
            foreach ($output as $message) {
                $context = $message->getContext();
                if (empty($context)) {
                    continue;
                }
                if (!empty($context["command"]) && strpos($context['command'], "composer why-not $package") === 0) {
                    $total_found++;
                }
            }
        }
        self::assertEquals(22, $total_found);
    }
}
