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
        foreach ($package_types as $package_type) {
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
                        continue;
                    }
                    $list[] = new UpdateListItem($package->name, $package->version, $old_package->version);
                }
            }
        }

        return $list;
    }
}
