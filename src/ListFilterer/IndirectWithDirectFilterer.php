<?php

namespace eiriksm\CosyComposer\ListFilterer;

use Violinist\ComposerLockData\ComposerLockData;

class IndirectWithDirectFilterer
{

    /**
     * @var string
     */
    protected $lockData;

    /**
     * @var string
     */
    protected $composerJson;

    const REQUIRE_TYPES = [
        'require',
        'require-dev',
    ];

    public function __construct($composer_lock, $composer_json)
    {
        $this->lockData = $composer_lock;
        $this->composerJson = $composer_json;
    }

    public static function create($composer_lock, $composer_json)
    {
        return new self($composer_lock, $composer_json);
    }

    public function filter(array $list)
    {
        $new_list = [];
        foreach ($list as $value) {
            // Find the reason we have this.
            $new_list = array_merge($this->findRequiresForPackage($value->name), $new_list);
        }
        // So, let's create just a fake report of the ones we want. They should for sure be "semver safe update",
        // either way.
        $lock_data = ComposerLockData::createFromString(json_encode($this->lockData));
        return array_map(function ($item) use ($lock_data) {
            $package = $lock_data->getPackageData($item);
            return (object) [
                'name' => $item,
                'version' => $package->version,
                'latest' => 'dependencies',
                'latest-status' => 'semver-safe-update',
            ];
        }, $new_list);
    }

    protected function findRequiresForPackage($package_name)
    {
        $package_name = mb_strtolower($package_name);
        // Loop over all packages, and see if any of the hits actually are required as top level require or require-dev.
        $types = [
            'packages',
            'packages-dev',
        ];
        // First see if it's actually directly from the composer.json
        if ($this->isInComposerJson($package_name)) {
            return [$package_name];
        }
        $requires = [];
        foreach ($types as $type) {
            if (empty($this->lockData->{$type})) {
                continue;
            }
            foreach ($this->lockData->{$type} as $package) {
                foreach (self::REQUIRE_TYPES as $req_type) {
                    if (empty($package->{$req_type})) {
                        continue;
                    }
                    foreach ($package->{$req_type} as $name => $version) {
                        // Now, this is a bit awkward. Version is not something we even check. But then again. Have it
                        // been installed, it should also have been compatible.
                        $name = mb_strtolower($name);
                        if ($name !== $package_name) {
                            continue;
                        }
                        // Now see if this is in fact a direct dependency itself.
                        $candidate = mb_strtolower($package->name);
                        if ($this->isInComposerJson($candidate)) {
                            $requires[] = $candidate;
                        } else {
                            $requires = array_merge($requires, $this->findRequiresForPackage($candidate));
                        }
                    }
                }
            }
        }
        return $requires;
    }

    protected function isInComposerJson($package_name)
    {
        foreach (self::REQUIRE_TYPES as $type) {
            if (empty($this->composerJson->{$type})) {
                continue;
            }
            foreach ($this->composerJson->{$type} as $name => $range) {
                $name = mb_strtolower($name);
                if ($name === $package_name) {
                    return true;
                }
            }
        }
    }
}
