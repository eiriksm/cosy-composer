<?php

namespace eiriksm\CosyComposer\Updater;

use Violinist\ComposerUpdater\Updater;

/**
 * Updater for grouped updates that need to go beyond the constraint.
 *
 * The base updater can only "require" a single package at a time. For a group
 * we want to bump the constraint of every package in the group in one single
 * "composer require", so that all of them can move beyond their current
 * constraint together (and composer can resolve them as a set).
 */
class GroupUpdater extends Updater
{
    /**
     * Map of package name to the constraint to require (for example "^2.0.1").
     *
     * @var array<string, string>
     */
    private array $requirePackages = [];

    /**
     * @param array<string, string> $requirePackages
     */
    public function setRequirePackages(array $requirePackages) : void
    {
        $this->requirePackages = $requirePackages;
    }

    /**
     * @param mixed $version
     *
     * @return array<int, array<int, string>>
     */
    protected function getRequireRecipes($version) : array
    {
        $packages = [];
        foreach ($this->requirePackages as $name => $version_string) {
            $packages[] = sprintf('%s:%s', $name, $version_string);
        }
        // Note that composer require would move the packages into the section
        // (require or require-dev) indicated by the presence of the --dev flag.
        // Which means the packages we are requiring here all have to belong to
        // the same section. It is the responsibility of the caller to make sure
        // of that.
        return [
            array_merge(array_filter([
                'composer',
                'require',
                $this->isDevPackage() ? '--dev' : '',
                '-n',
                '--no-ansi',
            ]), $packages),
        ];
    }
}
