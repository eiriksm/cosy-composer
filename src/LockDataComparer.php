<?php

namespace eiriksm\CosyComposer;

use eiriksm\ViolinistMessages\UpdateListItem;
use Violinist\ComposerLockData\ComposerLockData;

class LockDataComparer
{
    protected $beforeData;
    protected $afterData;

    public function __construct(\stdClass $before, \stdClass $after)
    {
        $this->beforeData = ComposerLockData::createFromString(json_encode($before));
        $this->afterData = ComposerLockData::createFromString(json_encode($after));
    }

    /**
     * @return UpdateListItem[]
     */
    public function getUpdateList()
    {
        $list = [];
        $package_types = [
            'packages',
            'packages-dev',
        ];
        $after_data = $this->afterData->getData();
        $before_data = $this->beforeData->getData();
        foreach ($package_types as $package_type) {
            foreach ($before_data->{$package_type} as $package) {
                // See if we can find that one in the afterdata.
                $new_package = null;
                try {
                    $new_package = $this->afterData->getPackageData($package->name);
                } catch (\Exception $e) {
                    // Since we can not find it after update, it was removed.
                }
                if (!$new_package) {
                    $list_item = new UpdateListItem($package->name, $package->version);
                    $list_item->setIsRemoved(true);
                    $list_item->setIsNew(false);
                    $list[] = $list_item;
                }
            }
            foreach ($after_data->{$package_type} as $package) {
                // See if we can find that one in the before data.
                $old_package = null;
                try {
                    $old_package = $this->beforeData->getPackageData($package->name);
                } catch (\Exception $e) {
                    // Must mean the package is new, it was not installed before.
                }
                if (!$old_package) {
                    // Must mean the package is new, it was not installed before.
                    $list[] = new UpdateListItem($package->name, $package->version);
                } else {
                    if ($old_package->version === $package->version) {
                        // Well, unless they are actually on for example dev-master. Then we compare the dist hashes.
                        if (empty($old_package->dist->reference) || empty($package->dist->reference)) {
                            continue;
                        }
                        if ($old_package->dist->reference === $package->dist->reference) {
                            continue;
                        }
                        $old_package->version = sprintf('%s#%s', $old_package->version, $old_package->dist->reference);
                        $package->version = sprintf('%s#%s', $package->version, $package->dist->reference);
                    }
                    $list[] = new UpdateListItem($package->name, $package->version, $old_package->version);
                }
            }
        }

        return $list;
    }
}
