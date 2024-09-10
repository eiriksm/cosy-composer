<?php

namespace eiriksm\CosyComposer\SecurityChecker;

interface SecurityCheckerInterface
{
    public function checkDirectory(string $dir) : array;
}