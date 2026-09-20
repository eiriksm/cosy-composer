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
        /** @var IndirectWithDirectFilterListItem[] $new_list */
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
        // Now let's merge this info so that the things can have all of the
        // packages that has updates in them, before we filter them.
        $list_of_lists = $this->mergeChildrenWithUpdates($list_of_lists);
        $already_added_register = [];
        // First we add the actual direct dependencies. That's at least what I
        // think is the plan behind this loop here.
        foreach ($list_of_lists as $list_list) {
            foreach ($list_list as $item) {
                if (empty($item->child_with_update)) {
                    continue;
                }
                if (empty($item->name)) {
                    continue;
                }
                if ($item->child_with_update !== $item->name) {
                    continue;
                }
                $already_added_register[$item->name] = true;
                $return[] = $item;
            }
        }
        // Then we add the rest.
        foreach ($list_of_lists as $list_list) {
            foreach ($list_list as $item) {
                if (!empty($already_added_register[$item->name])) {
                    continue;
                }
                $already_added_register[$item->name] = true;
                $return[] = $item;
            }
        }
        return $return;
    }

    protected function mergeChildrenWithUpdates(array $list_of_lists) : array
    {
        $new_list_of_lists = [];
        foreach ($list_of_lists as $list_item) {
            $new_inner_list = [];
            foreach ($list_item as $item) {
                $new_item = clone $item;
                $new_item->children_with_update = [];
                foreach ($list_of_lists as $inner_duplicated_item) {
                    foreach ($inner_duplicated_item as $item_from_inner) {
                        if ($item_from_inner->name != $item->name) {
                            continue;
                        }
                        if (in_array($item_from_inner->child_with_update, $new_item->children_with_update)) {
                            continue;
                        }
                        $new_item->children_with_update[] = $item_from_inner->child_with_update;
                    }
                }
                $new_inner_list[] = $new_item;
            }
            $new_list_of_lists[] = $new_inner_list;
        }
        return $new_list_of_lists;
    }
}
