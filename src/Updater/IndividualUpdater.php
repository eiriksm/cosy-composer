<?php

namespace eiriksm\CosyComposer\Updater;

use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use eiriksm\CosyComposer\CosyLogger;
use eiriksm\CosyComposer\GroupUpdateItem;
use eiriksm\CosyComposer\Helpers;
use eiriksm\CosyComposer\IndividualUpdateItem;
use eiriksm\CosyComposer\LockDataComparer;
use eiriksm\CosyComposer\Message;
use eiriksm\CosyComposer\ProcessFactoryWrapper;
use eiriksm\CosyComposer\Providers\NamedPrs;
use eiriksm\CosyComposer\PrParamsCreator;
use eiriksm\CosyComposer\UpdateItemInterface;
use eiriksm\ViolinistMessages\UpdateListItem;
use eiriksm\ViolinistMessages\ViolinistUpdate;
use Github\Exception\ValidationFailedException;
use Violinist\ComposerLockData\ComposerLockData;
use Violinist\ComposerUpdater\Exception\ComposerUpdateProcessFailedException;
use Violinist\ComposerUpdater\Exception\NotUpdatedException;
use Violinist\ComposerUpdater\Updater;
use Violinist\Config\Config;
use Violinist\ProjectData\ProjectData;

class IndividualUpdater extends BaseUpdater
{
    /**
     * @var string
     */
    private $composerJsonDir;

    /**
     * @var array<string, array<int, string>>
     */
    private $patternBundledPackages = [];

    /**
     * @var array<string, bool>
     */
    private $patternBundledFollowers = [];

    public function __construct()
    {
    }

    public function setComposerJsonDir($dir)
    {
        $this->composerJsonDir = $dir;
    }

    /**
     * {@inheritdoc}
     */
    public function handleUpdate($data, $lockdata, $cdata, $one_pr_per_dependency, $initial_lock_file_data, NamedPrs $prs_named, $default_base, $hostname, $default_branch, $alerts, $is_allowed_out_of_date_pr, Config $config)
    {
        $this->initialComposerLockData = $initial_lock_file_data;
        $can_update_beyond = $config->shouldAllowUpdatesBeyondConstraint();
        $max_number_of_prs = $config->getNumberOfAllowedPrs();
        $data = $this->convertDataToDto($data);
        $this->patternBundledPackages = [];
        $this->patternBundledFollowers = [];
        // And now convert the data to DTOs for the groups.
        $groups = self::createGroups($data, $config);
        // Now we have the groups, we can loop through them and handle them. In
        // the process we will also remove items from the individual items.
        foreach ($groups as $group) {
            // Alright, let's take the rule, and remove any items that are in
            // composer.json and also in the group.
            $has_match = false;
            foreach ($data as $key => $item) {
                // Now let's see if it matches the group, and remove it from the
                // updates.
                if (!$group->groupRuleMatches($item->getPackageName())) {
                    continue;
                }
                // Not sure how it can not have a match somewhere, but hey.
                $has_match = true;
                unset($data[$key]);
            }
            if ($has_match) {
                // Of course, also add it to the actual jobs we will be doing.
                $data[] = $group;
            }
        }
        $this->preparePatternBundles($data, $config);
        foreach ($data as $item) {
            $item_name = $item->getPackageName();
            if ($item instanceof IndividualUpdateItem && empty($this->patternBundledPackages[$item_name]) && !empty($this->patternBundledFollowers[$item_name])) {
                $this->log(sprintf('Skipping %s because it will be updated as part of a bundled pattern', $item_name));
                continue;
            }
            $security_update = false;
            $package_name_in_composer_json = $item_name;
            try {
                $package_name_in_composer_json = Helpers::getComposerJsonName($cdata, $item_name, $this->composerJsonDir);
            } catch (\Exception $e) {
            }
            if (isset($alerts[$package_name_in_composer_json])) {
                $security_update = true;
            }
            if ($max_number_of_prs && $this->getPrCount() >= $max_number_of_prs) {
                if ($security_update && $config->shouldAllowSecurityUpdatesOnConcurrentLimit()) {
                    $this->log(sprintf('The concurrent limit (%d) is reached, but the update of %s is a security update, so we will try to update it anyway.', $max_number_of_prs, $package_name_in_composer_json));
                } elseif (!in_array($item_name, $is_allowed_out_of_date_pr)) {
                    $this->log(
                        sprintf(
                            'Skipping %s because the number of max concurrent PRs (%d) seems to have been reached',
                            $item_name,
                            $max_number_of_prs
                        ),
                        Message::CONCURRENT_THROTTLED,
                        [
                            'package' => $item_name,
                        ]
                    );
                    continue;
                }
            }
            $this->handleUpdateItem(
                $item,
                $lockdata,
                $cdata,
                $one_pr_per_dependency,
                $initial_lock_file_data,
                $prs_named,
                $default_base,
                $hostname,
                $default_branch,
                $security_update,
                $config,
                $can_update_beyond
            );
        }
    }

    protected function handleGroup(GroupUpdateItem $item, $lockdata, $cdata, $one_pr_per_dependency, $lock_file_contents, NamedPrs $prs_named_object, $default_base, $hostname, $default_branch, bool $security_update, Config $global_config, $can_update_beyond)
    {
        $prs_named = $prs_named_object->getAllPrsNamed();
        // @todo: This should rather take the config from the rule.
        $config = $global_config;
        // Alright, its a group. Let's gather all the package names that are in
        // composer json, and also matches.
        $package_matches = [];
        $composer_json = json_decode(file_get_contents($this->composerJsonDir . '/composer.json'));
        if (!empty($composer_json->require)) {
            foreach ($composer_json->require as $key => $value) {
                if ($item->groupRuleMatches($key)) {
                    $package_matches[] = $key;
                }
            }
        }
        if (!empty($composer_json->{'require-dev'})) {
            foreach ($composer_json->{'require-dev'} as $key => $value) {
                if ($item->groupRuleMatches($key)) {
                    $package_matches[] = $key;
                }
            }
        }
        if (empty($package_matches)) {
            // That's very strange. Let's call it an error.
            throw new \RuntimeException('No packages found in composer.json that matches the group rule');
        }
        $branch_name = '';
        $pr_params = [];
        try {
            // Create a branch. This should be specified in the rule config, yeah?
            $rule = $item->getRule();
            if (empty($rule->name)) {
                throw new \RuntimeException('The group rule does not have a name');
            }
            // The branch will be based on either the name, which we now know
            // exists, or a slug.
            $branch_name = Helpers::createBranchNameForGroup($rule, $config);
            $this->switchBranch($branch_name);
            // Now let's update them.
            $array_copy = $package_matches;
            $package_name = array_shift($package_matches);
            $updater = $this->getUpdater($package_name);
            $updater->setPackagesToCheckHasUpdated($package_matches);
            array_shift($array_copy);
            if (!empty($array_copy)) {
                $updater->setBundledPackages($array_copy);
            }
            $updater->setWithUpdate($config->shouldUpdateWithDependencies());
            $updater->setRunScripts($config->shouldRunScripts());
            if (!$lock_file_contents) {
                throw new \Exception('The group update can not be run with composer require');
            } else {
                $this->log('Running composer update for package ' . $package_name);
                $updater->executeUpdate();
            }
            // I guess at this point we know that something updated. Which is good. Let's create a PR then.
            $pr_params_creator = $this->getPrParamsCreator();
            $new_lock_data = json_decode(file_get_contents($this->composerJsonDir . '/composer.lock'));
            $post_update_lock = ComposerLockData::createFromString(json_encode($new_lock_data));
            $comparer = new LockDataComparer($lockdata, $new_lock_data);
            $package_lock_data = ComposerLockData::createFromString(json_encode($lockdata));
            $update_list = $comparer->getUpdateList();
            $update_array = array_map(function (UpdateListItem $update) use ($lockdata, $package_lock_data, $post_update_lock) {
                $update_obj = new ViolinistUpdate();
                $update_obj->setName($update->getPackageName());
                $update_obj->setCurrentVersion($update->getOldVersion());
                $update_obj->setNewVersion($update->getNewVersion());
                $package_name = $update->getPackageName();
                $pre_update_data = $package_lock_data->getPackageData($package_name);
                $post_update_data = $post_update_lock->getPackageData($package_name);
                $version_from = $update->getOldVersion();
                $version_to = $update->getNewVersion();
                $this->log('Trying to retrieve changelog for ' . $package_name);
                $changelog = null;
                $changed_files = [];
                try {
                    $changelog = $this->retrieveChangeLog($package_name, $lockdata, $version_from, $version_to);
                    $update_obj->setChangelog($changelog->getAsMarkdown());
                    $this->log('Changelog retrieved');
                } catch (\Throwable $e) {
                    // If the changelog can not be retrieved, we can live with that.
                    $this->log('Exception for changelog: ' . $e->getMessage());
                }
                try {
                    $changed_files = $this->retrieveChangedFiles($package_name, $lockdata, $version_from, $version_to);
                    $update_obj->setChangedFiles($changed_files);
                    $this->log('Changed files retrieved');
                } catch (\Throwable $e) {
                    // If the changed files can not be retrieved, we can live with that.
                    $this->log('Exception for retrieving changed files: ' . $e->getMessage());
                }
                // Let's try to find all of the tags between those commit shas.
                $release_links = null;
                try {
                    $release_links = $this->getReleaseLinks($lockdata, $package_name, $pre_update_data, $post_update_data);
                    $update_obj->setPackageReleaseNotes($release_links);
                } catch (\Throwable $e) {
                    $this->log('Retrieving links to releases failed');
                }
                return $update_obj;
            }, $update_list);
            $body = $pr_params_creator->createBodyForGroup($rule->name, $update_array);
            $title = sprintf('Update group `%s`', $rule->name);
            $pr_params = $pr_params_creator->getPrParamsForGroup($this->forkUser, $this->isPrivate, $this->slug, $branch_name, $body, $title, $default_branch, $config);
            $this->commitFilesForGroup($rule->name, $config);
            $this->runAuthExport($hostname);
            $this->pushCode($branch_name, $default_base, $lock_file_contents);
            $pullRequest = $this->createPullrequest($pr_params);
        } catch (NotUpdatedException $e) {
            // Not updated because of the composer command, not the
            // restriction itself.
            $item_data_items = $item->getData();
            foreach ($item_data_items as $update_item) {
                $why_not_name = $update_item->name;
                $why_not_version = trim($update_item->latest);
                $not_updated_context = [
                    'package' => $why_not_name,
                ];
                $command = [
                    'composer',
                    'why-not',
                    $why_not_name,
                    $why_not_version,
                ];
                $this->execCommand($command, false);
                $this->log($this->getLastStdErr(), Message::COMMAND, [
                    'command' => implode(' ', $command),
                    'package' => $why_not_name,
                    'type' => 'stderr',
                ]);
                $this->log($this->getLastStdOut(), Message::COMMAND, [
                    'command' => implode(' ', $command),
                    'package' => $why_not_name,
                    'type' => 'stdout',
                ]);
                $this->log(sprintf('Package %s was not updated', $update_item->name), Message::NOT_UPDATED, $not_updated_context);
            }
        } catch (ValidationFailedException $e) {
            // @todo: Do some better checking. Could be several things, this.
            $this->handlePossibleUpdatePrScenario($e, $branch_name, $pr_params, $prs_named_object, $config, $security_update);
            // If it failed validation because it already exists, we also want to make sure all outdated PRs are
            // closed.
            $raw_item = $item->getData();
            if (!empty($prs_named[$branch_name]['number'])) {
                // @todo: Count the PR and close outdated.
            }
        } catch (\Gitlab\Exception\RuntimeException $e) {
            $this->handlePossibleUpdatePrScenario($e, $branch_name, $pr_params, $prs_named_object, $config, $security_update);
            if (!empty($prs_named[$branch_name]['number'])) {
                // @todo: Count the PR and close outdated.
            }
        } catch (ComposerUpdateProcessFailedException $e) {
            $this->log('Caught an exception: ' . $e->getMessage(), 'error');
            $this->log($e->getErrorOutput(), Message::COMMAND, [
                'type' => 'exit_code_output',
                'package' => $package_name,
            ]);
        } catch (\Throwable $e) {
            // @todo: Should probably handle this in some way.
            $this->log('Caught an exception: ' . $e->getMessage(), 'error');
        }
        $this->executePostUpdateStep($default_branch, $lock_file_contents, $config);
    }

    protected function handleUpdateItem(UpdateItemInterface $item_object, $lockdata, $cdata, $one_pr_per_dependency, $lock_file_contents, NamedPrs $prs_named, $default_base, $hostname, $default_branch, bool $security_update, Config $global_config, $can_update_beyond)
    {
        if ($item_object instanceof GroupUpdateItem) {
            return $this->handleGroup($item_object, $lockdata, $cdata, $one_pr_per_dependency, $lock_file_contents, $prs_named, $default_base, $hostname, $default_branch, $security_update, $global_config, $can_update_beyond);
        }
        if (!$item_object instanceof IndividualUpdateItem) {
            throw new \RuntimeException('The item object is not an instance of IndividualUpdateItem');
        }
        $item = $item_object->getRawData();
        // Default to global config.
        $config = $global_config;
        $should_indicate_can_not_update_if_unupdated = false;
        $package_name = $item->name;
        $branch_name = '';
        $pr_params = [];
        try {
            $package_lock_data = ComposerLockData::createFromString(json_encode($lockdata));
            $pre_update_data = $package_lock_data->getPackageData($package_name);
            $version_from = $item->version;
            $version_to = $item->latest;
            // See where this package is.
            try {
                $package_name_in_composer_json = Helpers::getComposerJsonName($cdata, $package_name, $this->composerJsonDir);
                $config = $global_config->getConfigForPackage($package_name_in_composer_json);
            } catch (\Exception $e) {
                // If this was a package that we somehow got because we have allowed to update other than direct
                // dependencies we can avoid re-throwing this.
                $config = $global_config->getConfigForPackage($package_name);
                if ($global_config->shouldCheckDirectOnly()) {
                    throw $e;
                }
                // Taking a risk :o.
                $package_name_in_composer_json = $package_name;
            }
            $req_item = '';
            $is_require_dev = false;
            if (!empty($cdata->{'require-dev'}->{$package_name_in_composer_json})) {
                $req_item = $cdata->{'require-dev'}->{$package_name_in_composer_json};
                $is_require_dev = true;
            } else {
                // @todo: Support getting req item from a merge plugin as well.
                if (isset($cdata->{'require'}->{$package_name_in_composer_json})) {
                    $req_item = $cdata->{'require'}->{$package_name_in_composer_json};
                }
            }
            $should_update_beyond = false;
            // See if the new version seems to satisfy the constraint. Unless the constraint is dev related somehow.
            try {
                if (strpos((string) $req_item, 'dev') === false && !Semver::satisfies($version_to, (string) $req_item)) {
                    // Well, unless we have actually disallowed this through config.
                    $should_update_beyond = true;
                    if (!$can_update_beyond) {
                        // Let's instead try to update within the constraint.
                        $should_update_beyond = false;
                        $should_indicate_can_not_update_if_unupdated = true;
                    }
                }
            } catch (\Exception $e) {
                // Could be, some times, that we try to check a constraint that semver does not recognize. That is
                // totally fine.
            }

            // Create a new branch.
            $branch_name = Helpers::createBranchName($item, $one_pr_per_dependency, $config);
            $this->switchBranch($branch_name);
            // Try to use the same version constraint.
            $version = (string) $req_item;
            // @todo: This is not nearly something that covers the world of constraints. Probably possible to use
            // something from composer itself here.
            $constraint = '';
            if (!empty($version[0])) {
                switch ($version[0]) {
                    case '^':
                        $constraint = '^';
                        break;

                    case '~':
                        $constraint = '~';
                        break;

                    default:
                        $constraint = '';
                        break;
                }
            }
            $update_with_deps = $config->shouldUpdateWithDependencies();
            $updater = $this->getUpdater($package_name);
            // See if this package has any bundled updates.
            $bundled_packages = $config->getBundledPackagesForPackage($package_name);
            if (!empty($this->patternBundledPackages[$package_name])) {
                $bundled_packages = array_merge($bundled_packages, $this->patternBundledPackages[$package_name]);
            }
            if (!empty($bundled_packages)) {
                $updater->setBundledPackages(array_values(array_unique($bundled_packages)));
            }
            $updater->setWithUpdate($update_with_deps);
            $updater->setConstraint($constraint);
            $updater->setDevPackage($is_require_dev);
            $updater->setRunScripts($config->shouldRunScripts());
            if ($config->shouldUpdateIndirectWithDirect()) {
                $updater->setShouldThrowOnUnupdated(false);
                if (!empty($item->child_with_update)) {
                    $updater->setShouldThrowOnUnupdated(true);
                    $updater->setPackagesToCheckHasUpdated([$item->child_with_update]);
                }
                // But really, we should now have an array, shouldn't we?
                if (!empty($item->children_with_update) && is_array($item->children_with_update)) {
                    $updater->setShouldThrowOnUnupdated(true);
                    $updater->setPackagesToCheckHasUpdated($item->children_with_update);
                }
            }
            if (!$lock_file_contents || ($should_update_beyond && $can_update_beyond)) {
                $updater->executeRequire($version_to);
            } else {
                if (!empty($item->child_with_update)) {
                    $this->log(sprintf('Running composer update for package %s to update the indirect dependency %s', $package_name, $item->child_with_update));
                } else {
                    $this->log('Running composer update for package ' . $package_name);
                }
                $updater->executeUpdate();
            }
            $post_update_data = $updater->getPostUpdateData();
            if (isset($post_update_data->source) && $post_update_data->source->type == 'git' && isset($pre_update_data->source)) {
                $version_from = $pre_update_data->source->reference;
                $version_to = $post_update_data->source->reference;
            }
            // Now, see if the update was actually to the version we are expecting.
            // If we are updating to another dev version, composer show will tell us something like:
            // dev-master 15eb463
            // while the post update data version will still say:
            // dev-master.
            // So to compare these, we compare the hashes, if the version latest we are updating to
            // matches the dev regex.
            if (preg_match('/dev-\S* /', $item->latest)) {
                $sha = preg_replace('/dev-\S* /', '', $item->latest);
                // Now if the version_to matches this, we have updated to the expected version.
                if (strpos($version_to, $sha) === 0) {
                    $post_update_data->version = $item->latest;
                }
            }
            // If the item->latest key is set to dependencies, we actually want to allow the branch to change, since
            // the version of the package will of course be an actual version instead of the version called
            // "latest".
            if ('dependencies' !== $item->latest && $post_update_data->version != $item->latest) {
                $new_item = (object) [
                    'name' => $item->name,
                    'version' => $item->version,
                    'latest' => $post_update_data->version,
                ];
                $new_branch_name = Helpers::createBranchName($new_item, $config->shouldUseOnePullRequestPerPackage(), $config);
                $is_an_actual_upgrade = Comparator::greaterThan($post_update_data->version, $item->version);
                $old_item_is_branch = strpos($item->version, 'dev-') === 0;
                $new_item_is_branch = strpos($post_update_data->version, 'dev-') === 0;
                if (!$old_item_is_branch && !$new_item_is_branch && !$is_an_actual_upgrade) {
                    throw new NotUpdatedException('The new version is lower than the installed version');
                }
                if ($branch_name !== $new_branch_name) {
                    $this->log(sprintf('Changing branch because of an unexpected update result. We expected the branch name to be %s but instead we are now switching to %s.', $branch_name, $new_branch_name));
                    $this->switchBranch($new_branch_name, false);
                    $branch_name = $new_branch_name;
                }
            }
            $this->log('Successfully ran command composer update for package ' . $package_name);
            $new_lock_data = json_decode(file_get_contents($this->composerJsonDir . '/composer.lock'));
            $list_item = new UpdateListItem($package_name, $post_update_data->version, $item->version);
            $this->log('Trying to retrieve changelog for ' . $package_name);
            $changelog = null;
            $changed_files = [];
            try {
                $changelog = $this->retrieveChangeLog($package_name, $lockdata, $version_from, $version_to);
                $this->log('Changelog retrieved');
            } catch (\Throwable $e) {
                // If the changelog can not be retrieved, we can live with that.
                $this->log('Exception for changelog: ' . $e->getMessage());
            }
            try {
                $changed_files = $this->retrieveChangedFiles($package_name, $lockdata, $version_from, $version_to);
                $this->log('Changed files retrieved');
            } catch (\Throwable $e) {
                // If the changed files can not be retrieved, we can live with that.
                $this->log('Exception for retrieving changed files: ' . $e->getMessage());
            }
            // Let's try to find all of the tags between those commit shas.
            $release_links = null;
            try {
                $release_links = $this->getReleaseLinks($lockdata, $package_name, $pre_update_data, $post_update_data);
            } catch (\Throwable $e) {
                $this->log('Retrieving links to releases failed');
            }
            $comparer = new LockDataComparer($lockdata, $new_lock_data);
            $update_list = $comparer->getUpdateList();
            $pr_params_creator = $this->getPrParamsCreator();
            $body = $pr_params_creator->createBody($item, $post_update_data, $changelog, $security_update, $update_list, $changed_files, $release_links);
            $title = $pr_params_creator->createTitle($item, $post_update_data, $security_update);
            if ($config->getDefaultBranch($security_update)) {
                $this->log('Default target branch branch from config is set to ' . $config->getDefaultBranch($security_update));
                $default_branch = $config->getDefaultBranch($security_update);
            }
            $pr_params = $pr_params_creator->getPrParams($this->forkUser, $this->isPrivate, $this->getSlug(), $branch_name, $body, $title, $default_branch, $config);
            // Check if this new branch name has a pr up-to-date.
            $prs_named_array = $prs_named->getAllPrsNamed();
            if (!Helpers::shouldUpdatePr($branch_name, $pr_params, $prs_named) && array_key_exists($branch_name, $prs_named_array)) {
                if (!$default_base) {
                    $this->log(sprintf('Skipping %s because a pull request already exists', $item->name), Message::PR_EXISTS, [
                        'package' => $item->name,
                    ]);
                    $this->countPR($item->name);
                    $pr_id = $prs_named_array[$branch_name]['number'];
                    $this->closeOutdatedPrsForPackage($item->name, $item->version, $config, $pr_id, $prs_named, $default_branch);
                    return;
                }
                // Is the pr up to date?
                if ($prs_named_array[$branch_name]['base']['sha'] == $default_base) {
                    $this->log(sprintf('Skipping %s because a pull request already exists', $item->name), Message::PR_EXISTS, [
                        'package' => $item->name,
                    ]);
                    $this->countPR($item->name);
                    $pr_id = $prs_named_array[$branch_name]['number'];
                    $this->closeOutdatedPrsForPackage($item->name, $item->version, $config, $pr_id, $prs_named, $default_branch);
                    return;
                }
            }
            $this->commitFilesForPackage($list_item, $config, $is_require_dev);
            $this->runAuthExport($hostname);
            $this->pushCode($branch_name, $default_base, $lock_file_contents);
            $pullRequest = $this->createPullrequest($pr_params);
            if (!empty($pullRequest['html_url'])) {
                $this->log($pullRequest['html_url'], Message::PR_URL, [
                    'package' => $package_name,
                ]);

                Helpers::handleAutoMerge($this->client, $this->logger, $this->slug, $config, $pullRequest, $security_update);
                $this->handleLabels($config, $pullRequest, $security_update);
                if (!empty($pullRequest['number'])) {
                    $this->closeOutdatedPrsForPackage($item->name, $item->version, $config, $pullRequest['number'], $prs_named, $default_branch);
                }
            }
            $this->countPR($item->name);
        } catch (NotUpdatedException $e) {
            // Not updated because of the composer command, not the
            // restriction itself.
            if ($should_indicate_can_not_update_if_unupdated && isset($package_name) && isset($req_item) && isset($version_to)) {
                $message = sprintf('Package %s with the constraint %s can not be updated to %s.', $package_name, $req_item, $version_to);
                $this->log($message, Message::UNUPDATEABLE, [
                    'package' => $package_name,
                ]);
            } else {
                $why_not_name = $original_name = $item->name;
                $why_not_version = trim($item->latest);
                $not_updated_context = [
                    'package' => $why_not_name,
                ];
                if (!empty($item->child_latest) && !empty($item->child_with_update)) {
                    $why_not_name = $item->child_with_update;
                    $why_not_version = trim($item->child_latest);
                    $not_updated_context['package'] = $why_not_name;
                    $not_updated_context['parent_package'] = $original_name;
                }
                $command = [
                    'composer',
                    'why-not',
                    $why_not_name,
                    $why_not_version,
                ];
                $this->execCommand($command, false);
                $this->log($this->getLastStdErr(), Message::COMMAND, [
                    'command' => implode(' ', $command),
                    'package' => $why_not_name,
                    'type' => 'stderr',
                ]);
                $this->log($this->getLastStdOut(), Message::COMMAND, [
                    'command' => implode(' ', $command),
                    'package' => $why_not_name,
                    'type' => 'stdout',
                ]);
                if (!empty($item->child_with_update)) {
                    $this->log(sprintf("%s was not updated running composer update for direct dependency %s", $item->child_with_update, $package_name), Message::NOT_UPDATED, $not_updated_context);
                } else {
                    $this->log("$package_name was not updated running composer update", Message::NOT_UPDATED, $not_updated_context);
                }
            }
        } catch (ValidationFailedException $e) {
            // @todo: Do some better checking. Could be several things, this.
            $this->handlePossibleUpdatePrScenario($e, $branch_name, $pr_params, $prs_named, $config, $security_update);
            // If it failed validation because it already exists, we also want to make sure all outdated PRs are
            // closed.
            $prs_named_array = $prs_named->getAllPrsNamed();
            if (!empty($prs_named_array[$branch_name]['number'])) {
                $this->countPR($item->name);
                $this->closeOutdatedPrsForPackage($item->name, $item->version, $config, $prs_named_array[$branch_name]['number'], $prs_named, $default_branch);
            }
        } catch (\Gitlab\Exception\RuntimeException $e) {
            $this->handlePossibleUpdatePrScenario($e, $branch_name, $pr_params, $prs_named, $config, $security_update);
            $prs_named_array = $prs_named->getAllPrsNamed();
            if (!empty($prs_named_array[$branch_name]['number'])) {
                $this->countPR($item->name);
                $this->closeOutdatedPrsForPackage($item->name, $item->version, $config, $prs_named_array[$branch_name]['number'], $prs_named, $default_branch);
            }
        } catch (ComposerUpdateProcessFailedException $e) {
            $this->log('Caught an exception: ' . $e->getMessage(), 'error');
            $this->log($e->getErrorOutput(), Message::COMMAND, [
                'type' => 'exit_code_output',
                'package' => $package_name,
            ]);
        } catch (\Throwable $e) {
            // @todo: Should probably handle this in some way.
            $this->log('Caught an exception: ' . $e->getMessage(), 'error', [
                'package' => $package_name,
            ]);
        }
        $this->executePostUpdateStep($default_branch, $lock_file_contents, $config);
    }

    protected function executePostUpdateStep($default_branch, $lock_file_contents, Config $config)
    {
        $this->log('Checking out default branch - ' . $default_branch);
        $checkout_default_exit_code = $this->execCommand(['git', 'checkout', $default_branch], false);
        if ($checkout_default_exit_code) {
            $this->log($this->getLastStdErr());
            throw new \Exception('There was an error trying to check out the default branch. The process ended with exit code ' . $checkout_default_exit_code);
        }
        // Also do a git checkout of the files, since we want them in the state they were on the default branch
        $this->execCommand(['git', 'checkout', '.'], false);
        // Re-do composer install to make output better, and to make the lock file actually be there for
        // consecutive updates, if it is a project without it.
        if (!$lock_file_contents) {
            $this->execCommand(['rm', 'composer.lock']);
        }
        try {
            $this->doComposerInstall($config);
        } catch (\Throwable $e) {
            $this->log('Rolling back state on the default branch was not successful. Subsequent updates may be affected');
        }
    }

    protected function handleLabels(Config $config, $pullRequest, $security_update = false) : void
    {
        $labels_allowed = false;
        $labels_allowed_roles = [
            'agency',
            'enterprise',
        ];
        if ($this->projectData instanceof ProjectData && $this->projectData->getRoles()) {
            foreach ($this->projectData->getRoles() as $role) {
                if (in_array($role, $labels_allowed_roles)) {
                    $labels_allowed = true;
                }
            }
        }
        if (!$labels_allowed) {
            return;
        }
        Helpers::handleLabels($this->getPrClient(), $this->getLogger(), $this->slug, $config, $pullRequest, $security_update);
    }

    protected function handlePossibleUpdatePrScenario(\Exception $e, $branch_name, $pr_params, NamedPrs $prs_named_obj, Config $config, $security_update = false)
    {
        $prs_named = $prs_named_obj->getAllPrsNamed();
        $this->log('Had a problem with creating the pull request: ' . $e->getMessage(), 'error');
        if (Helpers::shouldUpdatePr($branch_name, $pr_params, $prs_named_obj)) {
            $this->log('Will try to update the PR based on settings.');
            $this->getPrClient()->updatePullRequest($this->slug, $prs_named[$branch_name]['number'], $pr_params);
        }
        if (!empty($prs_named[$branch_name])) {
            Helpers::handleAutoMerge($this->client, $this->logger, $this->slug, $config, $prs_named[$branch_name], $security_update);
            $this->handleLabels($config, $prs_named[$branch_name], $security_update);
        }
    }

    protected function preparePatternBundles(array $items, Config $config) : void
    {
        $available_packages = [];
        foreach ($items as $candidate) {
            if (!$candidate instanceof IndividualUpdateItem) {
                continue;
            }
            $available_packages[$candidate->getPackageName()] = true;
        }
        if (empty($available_packages)) {
            return;
        }
        foreach ($items as $candidate) {
            if (!$candidate instanceof IndividualUpdateItem) {
                continue;
            }
            $package_name = $candidate->getPackageName();
            if (!empty($this->patternBundledFollowers[$package_name])) {
                continue;
            }
            $package_config = $config->getConfigForPackage($package_name);
            $bundled_for_pattern = $this->getBundledPackagesByPattern($package_config, $package_name, $items);
            if (empty($bundled_for_pattern)) {
                continue;
            }
            $valid_followers = array_values(array_filter($bundled_for_pattern, function ($name) use ($available_packages) {
                return isset($available_packages[$name]);
            }));
            if (empty($valid_followers)) {
                continue;
            }
            $this->patternBundledPackages[$package_name] = array_values(array_unique(array_merge($this->patternBundledPackages[$package_name] ?? [], $valid_followers)));
            foreach ($valid_followers as $follower) {
                $this->patternBundledFollowers[$follower] = true;
            }
        }
    }

    protected function getBundledPackagesByPattern(Config $config, string $package_name, array $all_items) : array
    {
        $config_data = $config->getConfig();
        if (empty($config_data->bundled_packages) || !is_object($config_data->bundled_packages)) {
            return [];
        }
        $bundled = [];
        foreach ($config_data->bundled_packages as $pattern => $packages) {
            if (!$this->patternContainsGlob($pattern)) {
                continue;
            }
            if (!fnmatch($pattern, $package_name)) {
                continue;
            }
            $targets = [];
            if (is_array($packages) && count($packages)) {
                $targets = $packages;
            } else {
                foreach ($all_items as $candidate) {
                    if (!$candidate instanceof IndividualUpdateItem) {
                        continue;
                    }
                    $candidate_name = $candidate->getPackageName();
                    if (fnmatch($pattern, $candidate_name)) {
                        $targets[] = $candidate_name;
                    }
                }
            }
            $targets[] = $package_name;
            $targets = array_values(array_unique($targets));
            sort($targets);
            if ($targets[0] !== $package_name) {
                continue;
            }
            $bundled = array_merge($bundled, array_diff($targets, [$package_name]));
        }
        $bundled = array_values(array_unique($bundled));
        return $bundled;
    }

    protected function patternContainsGlob(string $pattern) : bool
    {
        return strpbrk($pattern, '*?[') !== false;
    }

    /**
     * @return UpdateItemInterface[]
     */
    protected function convertDataToDto(array $data) : array
    {
        $new_items = [];
        foreach ($data as $item) {
            $new_items[] = new IndividualUpdateItem($item);
        }
        return $new_items;
    }

    /**
     * @param UpdateItemInterface[] $items
     * @return GroupUpdateItem[]
     */
    public static function createGroups(array $items, Config $config) : array
    {
        /** @var GroupUpdateItem[] $groups */
        $groups = [];
        $rules = $config->getRules();
        foreach ($items as $item) {
            if (!$item instanceof IndividualUpdateItem) {
                continue;
            }
            foreach ($rules as $rule) {
                $matches = $config->getMatcherFactory()->hasMatches($rule, $item->getPackageName());
                if (!$matches) {
                    continue;
                }
                // Well alright then. Let's create a group of it.
                $group = new GroupUpdateItem($rule, $item->getRawData(), $config);
                // We don't need multiple groups of the same rule. So create a
                // hash of it and use it as the key.
                $hash = md5(json_encode($rule));
                if (empty($groups[$hash])) {
                    $groups[$hash] = $group;
                    continue;
                }
                $groups[$hash]->addData($item->getRawData());
            }
        }
        return $groups;
    }

    protected function getPrParamsCreator() : PrParamsCreator
    {
        $pr_params_creator = new PrParamsCreator($this->messageFactory, $this->projectData);
        $pr_params_creator->setAssigneesAllowed($this->assigneesAllowed);
        $pr_params_creator->setLogger($this->getLogger());
        return $pr_params_creator;
    }

    protected function getUpdater(string $package_name) : Updater
    {
        $updater = new Updater($this->getCwd(), $package_name);
        $cosy_logger = new CosyLogger();
        $cosy_factory_wrapper = new ProcessFactoryWrapper();
        $cosy_factory_wrapper->setExecutor($this->executer);
        $cosy_logger->setLogger($this->getLogger());
        $updater->setLogger($cosy_logger);
        $updater->setProcessFactory($cosy_factory_wrapper);
        return $updater;
    }
}
