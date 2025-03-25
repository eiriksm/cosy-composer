<?php

namespace eiriksm\CosyComposer;

class IndividualUpdateItem implements UpdateItemInterface
{
    /**
     * @var \stdClass
     */
    private $data;

    public function __construct(\stdClass $data)
    {
        $this->data = $data;
    }

    public function getPackageName()
    {
        return $this->data->name;
    }

    public function getNewVersion()
    {
        return $this->data->latest;
    }

    public function getVersion()
    {
        return $this->data->version;
    }

    public function setNewVersion(string $version)
    {
        $this->data->latest = $version;
    }

    public function getRawData()
    {
        return $this->data;
    }

    public function setVersion(string $version)
    {
        $this->data->version = $version;
    }
}