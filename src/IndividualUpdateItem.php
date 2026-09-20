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

    public function getPackageName() : string
    {
        return $this->data->name;
    }

    public function getNewVersion() : string
    {
        return $this->data->latest;
    }

    public function getVersion() : string
    {
        return $this->data->version;
    }

    public function getRawData() : \stdClass
    {
        return $this->data;
    }
}
