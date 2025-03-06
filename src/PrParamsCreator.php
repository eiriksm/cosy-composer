<?php

namespace eiriksm\CosyComposer;

use eiriksm\ViolinistMessages\ViolinistMessages;
use eiriksm\ViolinistMessages\ViolinistUpdate;
use Psr\Log\LoggerAwareTrait;
use Violinist\Config\Config;
use Violinist\ProjectData\ProjectData;
use Violinist\Slug\Slug;

class PrParamsCreator
{
    use AssigneesAllowedTrait;
    use LoggerAwareTrait;

    /**
     * @var ViolinistMessages
     */
    private $messageFactory;

    /**
     * @var ProjectData|null
     */
    private $projectData;

    public function __construct(ViolinistMessages $messageFactory, ?ProjectData $projectData = null)
    {
        $this->messageFactory = $messageFactory;
        $this->projectData = $projectData;
    }

    public function setAssigneesAllowed(bool $assigneesAllowed)
    {
        $this->assigneesAllowed = $assigneesAllowed;
    }

    /**
     * Helper to create body.
     */
    public function createBody($item, $post_update_data, $changelog = null, $security_update = false, array $update_list = [], $changed_files = [], $release_notes_for_package = [])
    {
        $update = new ViolinistUpdate();
        $update->setName($item->name);
        $update->setCurrentVersion($item->version);
        $update->setNewVersion($post_update_data->version);
        $update->setSecurityUpdate($security_update);
        if ($changelog) {
            /** @var \Violinist\GitLogFormat\ChangeLogData $changelog */
            $update->setChangelog($changelog->getAsMarkdown());
        }
        if ($this->projectData && $this->projectData->getCustomPrMessage()) {
            $update->setCustomMessage($this->projectData->getCustomPrMessage());
        }
        $update->setUpdatedList($update_list);
        if ($changed_files) {
            $update->setChangedFiles($changed_files);
        }
        if ($release_notes_for_package) {
            $update->setPackageReleaseNotes($release_notes_for_package);
        }
        return $this->messageFactory->getPullRequestBody($update);
    }

    /**
     * Creates a title for a PR.
     *
     * @param \stdClass $item
     *   The item in question.
     *
     * @return string
     *   A string ready to use.
     */
    public function createTitle($item, $post_update_data, $security_update = false)
    {
        $update = new ViolinistUpdate();
        $update->setName($item->name);
        $update->setCurrentVersion($item->version);
        $update->setNewVersion($post_update_data->version);
        $update->setSecurityUpdate($security_update);
        if ($item->version === $post_update_data->version) {
            // I guess we are updating the dependencies? We are surely not updating from one version to the same.
            return sprintf('Update dependencies of %s', $item->name);
        }
        return trim($this->messageFactory->getPullRequestTitle($update));
    }

    public function getPrParams($fork_user, bool $is_private, Slug $slug, $branch_name, $body, $title, $default_branch, Config $config)
    {
        $head = $fork_user . ':' . $branch_name;
        if ($is_private) {
            $head = $branch_name;
        }
        if ($slug->getProvider() === 'bitbucket.org') {
            // Currently does not support having the collapsible section thing.
            // @todo: Revisit from time to time?
            // @todo: Make sure we replace the correct one. What if the changelog has this in it?
            $body = str_replace([
                '<details>',
                '<summary>',
                '</summary>',
                '</details>',
            ], '', $body);
        }
        $assignees = $config->getAssignees();
        $assignees_allowed = $this->getAssigneesAllowed();
        if (!$assignees_allowed) {
            // Log a message so it's possible to understand why.
            if (!empty($assignees)) {
                if ($is_private) {
                    $assignees = [];
                    $this->logger->log('info', 'Assignees on private projects are only allowed on the agency and enterprise plan, or when running violinist self-hosted. Configuration was detected for assignees, but will be ignored');
                } else {
                    $this->logger->log('info', 'Assignees on private projects are only allowed on the agency and enterprise plan. This project was detected to be public, so assignees will still apply even though a sufficient plan is not active');
                }
            }
        }
        return [
            'base'  => $default_branch,
            'head'  => $head,
            'title' => $title,
            'body'  => $body,
            'assignees' => $assignees,
        ];
    }

    private function getAssigneesAllowed() : bool
    {
        $assignees_allowed_roles = [
            'agency',
            'enterprise',
        ];
        if ($this->projectData && $this->projectData->getRoles()) {
            foreach ($this->projectData->getRoles() as $role) {
                if (in_array($role, $assignees_allowed_roles)) {
                    return true;
                }
            }
        }
        return $this->assigneesAllowed;
    }
}
