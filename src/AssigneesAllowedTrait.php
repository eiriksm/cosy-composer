<?php

namespace eiriksm\CosyComposer;

trait AssigneesAllowedTrait
{
    protected $assigneesAllowed = false;

    public function setAssigneesAllowed(bool $assigneesAllowed)
    {
        $this->assigneesAllowed = $assigneesAllowed;
    }
}
