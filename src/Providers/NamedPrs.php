<?php

namespace eiriksm\CosyComposer\Providers;

use eiriksm\CosyComposer\Helpers;
use Symfony\Component\Yaml\Yaml;

class NamedPrs
{
    private $prs = [];
    private $knownPackagePrs = [];
    private $closedPackagePrs = [];

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

    public function addFromCommit(string $commit_message, array $pr_data, bool $is_open = true) : void
    {
        if (strpos($commit_message, Helpers::getCommitMessageSeparator()) === false) {
            return;
        }
        try {
            [$_discard, $data] = explode(Helpers::getCommitMessageSeparator(), $commit_message, 2);
            $yaml = Yaml::parse($data);
            $package_names = $this->getPackageNamesFromUpdateData($yaml['update_data'] ?? null);
            foreach ($package_names as $package_name) {
                if ($is_open) {
                    if (empty($this->knownPackagePrs[$package_name])) {
                        $this->knownPackagePrs[$package_name] = [];
                    }
                    $this->knownPackagePrs[$package_name][] = $pr_data;
                    continue;
                }
                if (empty($this->closedPackagePrs[$package_name])) {
                    $this->closedPackagePrs[$package_name] = [];
                }
                $this->closedPackagePrs[$package_name][] = $pr_data;
            }
        } catch (\Throwable $e) {
            // Not possible then, I guess.
        }
    }

    private function getPackageNamesFromUpdateData($update_data) : array
    {
        if (!is_array($update_data)) {
            return [];
        }
        if (!empty($update_data['package']) && is_string($update_data['package'])) {
            return [$update_data['package']];
        }
        $package_names = [];
        foreach ($update_data as $item) {
            if (!is_array($item) || empty($item['package']) || !is_string($item['package'])) {
                continue;
            }
            $package_names[] = $item['package'];
        }
        return array_values(array_unique($package_names));
    }

    public function getAllPrsNamed()
    {
        $named = [];
        foreach ($this->prs as $name => $pr) {
            $named[$name] = $pr;
        }
        foreach ($this->knownPackagePrs as $package => $prs) {
            foreach ($prs as $pr) {
                $named[$pr['head']['ref']] = $pr;
            }
        }
        return $named;
    }

    public function getKnownPackageNames() : array
    {
        return array_keys($this->knownPackagePrs);
    }

    public function getPrsFromPackage(string $package) : array
    {
        if (!empty($this->knownPackagePrs[$package])) {
            return $this->knownPackagePrs[$package];
        }
        return [];
    }

    public function getClosedPrsFromPackage(string $package) : array
    {
        if (!empty($this->closedPackagePrs[$package])) {
            return $this->closedPackagePrs[$package];
        }
        return [];
    }

    public function getOpenPrsWithPackages() : array
    {
        $requests = [];
        foreach ($this->knownPackagePrs as $package => $prs) {
            foreach ($prs as $pr) {
                if (empty($pr['number'])) {
                    continue;
                }
                $number = (string) $pr['number'];
                if (empty($requests[$number])) {
                    $requests[$number] = [
                        'pr' => $pr,
                        'packages' => [],
                    ];
                }
                $requests[$number]['packages'][] = $package;
            }
        }
        foreach ($requests as &$request) {
            $request['packages'] = array_values(array_unique($request['packages']));
        }
        unset($request);
        return array_values($requests);
    }

    public function markPrClosed($pr_number, string $closed_at) : void
    {
        foreach ($this->knownPackagePrs as $package => $prs) {
            foreach ($prs as $delta => $pr) {
                if (empty($pr['number']) || (string) $pr['number'] !== (string) $pr_number) {
                    continue;
                }
                $pr['state'] = 'closed';
                $pr['closed_at'] = $closed_at;
                if (empty($this->closedPackagePrs[$package])) {
                    $this->closedPackagePrs[$package] = [];
                }
                $this->closedPackagePrs[$package][] = $pr;
                unset($this->knownPackagePrs[$package][$delta]);
            }
            if (empty($this->knownPackagePrs[$package])) {
                unset($this->knownPackagePrs[$package]);
            }
        }
        foreach ($this->prs as $branch => $pr) {
            if (!empty($pr['number']) && (string) $pr['number'] === (string) $pr_number) {
                unset($this->prs[$branch]);
            }
        }
    }
}
