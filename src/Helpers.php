<?php

namespace eiriksm\CosyComposer;

use Psr\Log\LoggerInterface;
use Violinist\Config\Config;
use Violinist\Slug\Slug;

class Helpers
{

    public static function createBranchNameForGroup(\stdClass $rule, Config $config) : string
    {
        if (!empty($rule->slug)) {
            return $rule->slug;
        }
        // Create a slug based on the name. To do that, we  lowercase it, and
        // remove all the characters that are not a-z.
        $name = preg_replace('/[^a-z]+/', '', strtolower($rule->name));
        return self::createBranchNameFromNameAndConfig($name, $config);
    }

    /**
     * Helper to create branch name.
     */
    public static function createBranchName($item, $one_per_package = false, $config = null)
    {
        if ($one_per_package) {
            $name = sprintf('violinist%s', self::createBranchNameFromVersions($item->name, '', ''));
            if ($config) {
                /** @var Config $config */
                return self::createBranchNameFromNameAndConfig($name, $config);
            }
            return $name;
        }
        return self::createBranchNameFromVersions($item->name, $item->version, $item->latest, $config);
    }

    public static function createBranchNameFromNameAndConfig(string $name, Config $config)
    {
        return sprintf('%s%s', $config->getBranchPrefix(), $name);
    }

    public static function getCommitMessageSeparator()
    {
        // Workaround for not being able to define a constant inside a trait.
        return '------';
    }

    public static function createBranchNameFromVersions($package, $version_from, $version_to, $config = null)
    {
        $item_string = sprintf('%s%s%s', $package, $version_from, $version_to);
        // @todo: Fix this properly.
        $result = preg_replace('/[^a-zA-Z0-9]+/', '', $item_string);
        $prefix = '';
        if ($config) {
            /** @var Config $config */
            $prefix = $config->getBranchPrefix();
        }
        return $prefix.$result;
    }

    public static function getComposerJsonName($cdata, $name, $tmp_dir)
    {
        if (!empty($cdata->{'require-dev'}->{$name})) {
            return $name;
        }
        if (!empty($cdata->require->{$name})) {
            return $name;
        }
        // If we can not find it, we have to search through the names, and try to normalize them. They could be in the
        // wrong casing, for example.
        $possible_types = [
            'require',
            'require-dev',
        ];
        foreach ($possible_types as $type) {
            if (empty($cdata->{$type})) {
                continue;
            }
            foreach ($cdata->{$type} as $package => $version) {
                if (strtolower($package) == strtolower($name)) {
                    return $package;
                }
            }
        }
        if (!empty($cdata->extra->{"merge-plugin"})) {
            $keys = [
                'include',
                'require',
            ];
            foreach ($keys as $key) {
                if (isset($cdata->extra->{"merge-plugin"}->{$key})) {
                    foreach ($cdata->extra->{"merge-plugin"}->{$key} as $extra_json) {
                        $files = glob(sprintf('%s/%s', $tmp_dir, $extra_json));
                        if (!$files) {
                            continue;
                        }
                        foreach ($files as $file) {
                            $contents = @file_get_contents($file);
                            if (!$contents) {
                                continue;
                            }
                            $json = @json_decode($contents);
                            if (!$json) {
                                continue;
                            }
                            try {
                                return self::getComposerJsonName($json, $name, $tmp_dir);
                            } catch (\Exception $e) {
                              // Fine.
                            }
                        }
                    }
                }
            }
        }
        throw new \Exception('Could not find ' . $name . ' in composer.json.');
    }

    public static function shouldUpdatePr($branch_name, $pr_params, $prs_named)
    {
        if (empty($branch_name)) {
            return false;
        }
        if (empty($pr_params)) {
            return false;
        }
        if (!empty($prs_named[$branch_name]['title']) && $prs_named[$branch_name]['title'] != $pr_params['title']) {
            return true;
        }
        if (!empty($prs_named[$branch_name]['body']) && !empty($pr_params['body'])) {
            if (trim($prs_named[$branch_name]['body']) != trim($pr_params['body'])) {
                return true;
            }
        }
        return false;
    }

    public static function handleAutoMerge(ProviderInterface $client, LoggerInterface $logger, Slug $slug, Config $config, $pullRequest, $security_update = false)
    {
        if ($config->shouldAutoMerge($security_update)) {
            $logger->log('info', 'Config indicated automerge should be enabled, Trying to enable automerge');
            $result = $client->enableAutomerge($pullRequest, $slug, $config->getAutomergeMethod($security_update));
            if (!$result) {
                $logger->log('info', 'Enabling automerge failed.');
            }
        }
    }

    public static function handleLabels(ProviderInterface $client, LoggerInterface $logger, Slug $slug, Config $config, $pullRequest, $security_update = false)
    {
        $labels = $config->getLabels();
        if ($security_update) {
            $labels = array_merge($labels, $config->getLabelsSecurity());
        }
        if (empty($labels)) {
            return;
        }
        $logger->log('info', 'Trying to add labels to PR');
        $result = $client->addLabels($pullRequest, $slug, $labels);
        if (!$result) {
            $logger->log('info', 'Error adding labels');
        } else {
            $logger->log('info', 'Labels added successfully');
        }
    }
}
