<?php

namespace eiriksm\CosyComposer\ListFilterer;

use Violinist\ComposerLockData\ComposerLockData;

class IndirectWithDirectFilterer implements FilterInterface
{
    use RequiresForPackageTrait;

    /**
     * @var \stdClass
     */
    protected $lockData;

    /**
     * @var \stdClass
     */
    protected $composerJson;

    public function __construct($composer_lock, $composer_json)
    {
        $this->lockData = $composer_lock;
        $this->composerJson = $composer_json;
    }

    public static function create($composer_lock, $composer_json)
    {
        return new self($composer_lock, $composer_json);
    }

    /**
     * {@inheritdoc}
     */
    public function filter(array $list) : array
    {
        $new_list = [];
        foreach ($list as $value) {
            // Find the reason we have this.
            $new_list = array_merge($this->findRequiresForPackage($value), $new_list);
        }
        // So, let's create just a fake report of the ones we want. They should for sure be "semver safe update",
        // either way.
        $lock_data = ComposerLockData::createFromString(json_encode($this->lockData));
        return array_map(function ($item) use ($lock_data) {
            $package = $lock_data->getPackageData($item->name);
            return (object) [
                'name' => $item->name,
                'version' => $package->version,
                'latest' => $item->latest ?? 'dependencies',
                'latest-status' => $item->{"latest-status"} ?? 'semver-safe-update',
            ];
        }, $new_list);
    }
}
