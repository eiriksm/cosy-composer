<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test for a default commit message.
 */
class ConfigBranchOrderTest extends ComposerUpdateIntegrationBase
{
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.0.2';

    public function setUp() : void
    {
        parent::setUp();
        $this->createComposerFileFromFixtures($this->dir, 'composer-master.json');
    }

    public function testUpdateInCorrectBranch()
    {
        $this->runtestExpectedOutput();
        // Since we don't place the updated composer.lock file, we fully expect the package to not be updated.
        // This might also of course be the case for the actual bug we are testing here, but since we already know that
        // the lock file in master contains no packages, then this demonstrates that this bug prevents the config branch
        // files from being read. Totally.
        $this->assertOutputContainsMessage('psr/log was not updated running composer update', $this->cosy);
    }

    protected function handleExecutorReturnCallback($cmd, &$return)
    {
        if ($cmd === ['git', 'checkout', 'develop']) {
            $this->createComposerFileFromFixtures($this->dir, 'composer-develop.json');
            $this->placeComposerLockContentsFromFixture('composer-develop.lock', $this->dir);
        }
    }

    protected function placeInitialComposerLock()
    {
        $this->placeComposerLockContentsFromFixture('composer-master.lock', $this->dir);
    }
}
