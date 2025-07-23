<?php

namespace eiriksm\CosyComposer\Providers;

use eiriksm\CosyComposer\Helpers;
use Symfony\Component\Yaml\Yaml;

class NamedPrs
{
    private $prs = [];
    private $knownPackagePrs = [];

    public static function createFromArray(array $prs) : NamedPrs
    {
        $value = new self();
        foreach ($prs as $pr) {
            $value->addFromPrData($pr);
        }
        return $value;
    }

    public function addFromPrData(array $pr) : void
    {
        $this->prs[$pr['head']['ref']] = $pr;
    }

    public function addFromCommit(string $commit_message, array $pr_data) : void
    {
        // Try to parse the commit message to find a package name. It's
        // formatted like this:
        // Commit subject
        // ---- (separator from Helpers::getCommitMessageSeparator
        // Yaml of update data.
        if (strpos($commit_message, Helpers::getCommitMessageSeparator()) === false) {
            return;
        }
        try {
            [$_discard, $data] = explode(Helpers::getCommitMessageSeparator(), $commit_message);
            $yaml = Yaml::parse($data);
            if (!empty($yaml["update_data"]["package"])) {
                if (empty($this->knownPackagePrs[$yaml["update_data"]["package"]])) {
                    $this->knownPackagePrs[$yaml["update_data"]["package"]] = [];
                }
                $this->knownPackagePrs[$yaml["update_data"]["package"]][] = $pr_data;
            }
        } catch (\Throwable $e) {
            // Not possible then, I guess.
        }
    }

    public function getAllPrsNamed()
    {
        $named = [];
        foreach ($this->prs as $name => $pr) {
            $named[$name] = $pr;
        }
        // Also add the known package PRs.
        foreach ($this->knownPackagePrs as $package => $prs) {
            foreach ($prs as $pr) {
                $named[$pr['head']['ref']] = $pr;
            }
        }
        return $named;
    }

    public function getPrsFromPackage(string $package) : array
    {
        $relevant_prs = [];
        // First see if its in the known package Prs.
        if (!empty($this->knownPackagePrs[$package])) {
            $relevant_prs = $this->knownPackagePrs[$package];
        }
        return $relevant_prs;
    }
}
