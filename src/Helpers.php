<?php

namespace eiriksm\CosyComposer;

class Helpers
{

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
}
