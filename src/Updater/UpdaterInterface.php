<?php

namespace eiriksm\CosyComposer\Updater;

use Violinist\Config\Config;

interface UpdaterInterface
{
    public function handleUpdate($data, $lockdata, $cdata, $one_pr_per_dependency, $initial_lock_file_data, $prs_named, $default_base, $hostname, $default_branch, $alerts, $is_allowed_out_of_date_pr, Config $config);
}
