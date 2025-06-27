<?php

namespace eiriksm\CosyComposer\Providers;

class NamedPrs
{
    private $prs = [];
    private $knownPackagePrs = [];

    public function addFromPrData(array $pr) : void
    {
        $this->prs[$pr['head']['ref']] = $pr;
    }

    public function addFromCommit(string $commit, array $pr) : void
    {
        // Try to parse the commit message to find a package name. It's
        // formatted like this:
        
    }
}