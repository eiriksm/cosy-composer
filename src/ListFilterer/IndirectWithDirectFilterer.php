<?php

namespace eiriksm\CosyComposer\ListFilterer;

use eiriksm\CosyComposer\IndirectWithDirectFilterListItem;
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
        /** @var IndirectWithDirectFilterListItem $new_list */
        $new_list = [];
        foreach ($list as $value) {
            // Find the reason we have this.
            $list_additions = $this->findRequiresForPackage($value, $value->name);
            $item = new IndirectWithDirectFilterListItem($value->name, $list_additions, $value->latest);
            $new_list[] = $item;
        }
        // So, let's create just a fake report of the ones we want. They should for sure be "semver safe update",
        // either way.
        $lock_data = ComposerLockData::createFromString(json_encode($this->lockData));
        $list_of_lists = array_map(function (IndirectWithDirectFilterListItem $list_item) use ($lock_data) {
            $return = [];
            foreach ($list_item->getReasons() as $reason) {
                if (!$this->isInComposerJson($reason->name)) {
                    continue;
                }
                $package = $lock_data->getPackageData($reason->name);
                $return[] = (object) [
                    'name' => $reason->name,
                    'version' => $package->version,
                    'latest' => $reason->latest ?? 'dependencies',
                    'latest-status' => $reason->{"latest-status"} ?? 'semver-safe-update',
                    'child_with_update' => $list_item->getName(),
                    'child_latest' => $list_item->getLatestVersion(),
                ];
            }
            return $return;
        }, $new_list);
        $return = [];
        foreach ($list_of_lists as $list_list) {
            foreach ($list_list as $item) {
                $return[] = $item;
            }
        }
        return $return;
    }
}
