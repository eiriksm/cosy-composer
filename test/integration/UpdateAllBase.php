<?php

namespace eiriksm\CosyComposerTest\integration;

use Violinist\Slug\Slug;
use Violinist\SymfonyCloudSecurityChecker\SecurityChecker;

abstract class UpdateAllBase extends Base
{

    protected $composerJson = 'composer.allow_all.json';
    protected $composerLock = 'composer.allow_all.lock';
    protected $foundCommit = false;
    protected $foundCommand = false;
    protected $foundBranch = false;
    protected $branchName = 'violinistall';
    protected $usesDirect = false;

    public function setUp() : void
    {
        parent::setUp();
        $this->createComposerFileFromFixtures($this->dir, $this->composerJson);
        $mock_output = $this->getMockOutputWithUpdate('psr/log', '1.0.0', '1.1.4');
        $this->placeComposerLockContentsFromFixture($this->composerLock, $this->dir);
        $this->cosy->setOutput($mock_output);
        $this->setDummyGithubProvider();
        $this->setupMockExecuter();
    }

    protected function setupMockExecuter()
    {
        $executor = $this->getMockExecuterWithReturnCallback(function ($command) {
            // We are looking for the very blindly calling of composer update.
            if ($command === ['composer', 'update']) {
                $this->foundCommand = true;
                // We also want to place the updated lock file there.
                $this->placeComposerLockContentsFromFixture($this->composerLock . '.updated', $this->dir);
            }
            $cmd = implode(' ', $command);
            if (mb_strpos($cmd, 'Update all dependencies')) {
                $this->foundCommit = true;
            }
            $branch_command = ['git', 'checkout', '-b', $this->branchName];
            if ($command === $branch_command) {
                $this->foundBranch = true;
            }
        });
        $this->cosy->setExecuter($executor);
    }
}
