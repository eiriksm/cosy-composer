<?php

namespace eiriksm\CosyComposer;

class IndirectWithDirectFilterListItem
{
    private $name = '';
    private $reason = [];
    private $latestVersion = '';

    public function __construct($package_name, $indirect_list, $latest_version) {
        $this->name = $package_name;
        $this->reason = $indirect_list;
        $this->latestVersion = $latest_version;
    }

    public function getLatestVersion() : string
    {
        return $this->latestVersion;
    }

    public function getReasons() : array
    {
        return  $this->reason;
    }

    public function getName() : string
    {
        return $this->name;
    }
}
