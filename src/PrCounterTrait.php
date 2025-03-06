<?php

namespace eiriksm\CosyComposer;

trait PrCounterTrait
{
    /**
     * @var PrCounter
     */
    protected $prCounter;

    public function getPrCounter() : PrCounter
    {
        if (!$this->prCounter instanceof PrCounter) {
            $this->prCounter = new PrCounter();
        }
        return $this->prCounter;
    }

    public function setPrCounter(PrCounter $prCounter) : void
    {
        $this->prCounter = $prCounter;
    }

    protected function getPrCount() : int
    {
        return $this->getPrCounter()->getPrCount();
    }

    protected function countPr(string $item) : void
    {
        $this->getPrCounter()->countPr($item);
    }
}
