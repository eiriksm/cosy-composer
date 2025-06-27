<?php

namespace eiriksm\CosyComposer\Providers;

use eiriksm\CosyComposer\Helpers;
use Symfony\Component\Yaml\Yaml;

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
        // Commit subject
        // ---- (separator from Helpers::getCommitMessageSeparator
        // Yaml of update data.
        try {
            [$_discard, $data] = explode(Helpers::getCommitMessageSeparator(), $commit);
            $yaml = Yaml::parse($data);
            if (!empty($yaml["update_data"]["package"])) {
                $this->knownPackagePrs[$yaml["update_data"]["package"]] = $pr;
            }
        } catch (\Throwable $e) {
            // Not possible then, I guess.
        }
    }

    public function getPrsFromPackage(string $package) : array
    {
        $relevant_prs = [];
        // First see if its in the known package Prs.
        if (!empty($this->knownPackagePrs[$package])) {
            $relevant_prs[] = $this->knownPackagePrs[$package];
        }
        return $relevant_prs;
    }
}
