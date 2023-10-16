<?php

namespace eiriksm\CosyComposer\ListFilterer;

trait RequiresForPackageTrait
{
    private $scannedCache = [];

    protected function findRequiresForPackage($package_obj, $initial_package = null)
    {
        $package_name = mb_strtolower($package_obj->name);
        $key = $package_name;
        if (!empty($initial_package)) {
            $key = $initial_package;
        }
        if (empty($this->scannedCache[$key])) {
            $this->scannedCache[$key] = [];
        }
        // Loop over all packages, and see if any of the hits actually are required as top level require or require-dev.
        $types = [
            'packages',
            'packages-dev',
        ];
        // First see if it's actually directly from the composer.json
        if ($this->isInComposerJson($package_name)) {
            return [$package_obj];
        }
        $requires = [];
        foreach ($types as $type) {
            if (empty($this->lockData->{$type})) {
                continue;
            }
            foreach ($this->lockData->{$type} as $package) {
                foreach (FilterInterface::REQUIRE_TYPES as $req_type) {
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
                        if (in_array($candidate, $this->scannedCache[$key])) {
                            continue;
                        }
                        $this->scannedCache[$key][] = $candidate;

                        if ($this->isInComposerJson($candidate)) {
                            $requires[] = (object) [
                                'name' => $candidate,
                            ];
                        } else {
                            $requires = array_merge($requires, $this->findRequiresForPackage($package, $initial_package));
                        }
                    }
                }
            }
        }
        return $requires;
    }

    protected function isInComposerJson($package_name)
    {
        $type = $this->getRequireTypeInComposerJsonForPackage($package_name);
        return (bool) $type;
    }

    protected function getRequireTypeInComposerJsonForPackage($package_name)
    {
        $package_name = mb_strtolower($package_name);
        foreach (FilterInterface::REQUIRE_TYPES as $type) {
            if (empty($this->composerJson->{$type})) {
                continue;
            }
            foreach ($this->composerJson->{$type} as $name => $range) {
                $name = mb_strtolower($name);
                if ($name === $package_name) {
                    return $type;
                }
            }
        }
        return null;
    }
}
