<?php

namespace eiriksm\CosyComposer;

class PrCounter
{

    /**
     * @var bool[]
     */
    private $openRelevantPrs = [];

    public function countPr(string $item) : void
    {
        $package_lower = strtolower($item);
        $this->openRelevantPrs[$package_lower] = true;
    }

    public function getPrCount() : int
    {
        return (int) count($this->openRelevantPrs);
    }
}
