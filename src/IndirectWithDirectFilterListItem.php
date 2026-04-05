<?php

namespace eiriksm\CosyComposer;

class IndirectWithDirectFilterListItem
{
    /** @var string */
    private $name = '';
    /** @var array<mixed> */
    private $reason = [];
    /** @var string */
    private $latestVersion = '';

    /**
     * @param array<mixed> $indirect_list
     */
    public function __construct(string $package_name, array $indirect_list, string $latest_version)
    {
        $this->name = $package_name;
        $this->reason = $indirect_list;
        $this->latestVersion = $latest_version;
    }

    public function getLatestVersion() : string
    {
        return $this->latestVersion;
    }

    /**
     * @return array<mixed>
     */
    public function getReasons() : array
    {
        return  $this->reason;
    }

    public function getName() : string
    {
        return $this->name;
    }
}
