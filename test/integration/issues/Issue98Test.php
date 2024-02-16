<?php

namespace eiriksm\CosyComposerTest\integration\issues;

use eiriksm\CosyComposerTest\integration\ComposerUpdateIntegrationBase;

/**
 * Class Issue98Test.
 *
 * Issue 98 was that after we switched the change log fetcher, we forgot to set the auth on the fetcher, so private
 * repos were not fetched with auth tokens set.
 */
class Issue98Test extends ComposerUpdateIntegrationBase
{

    protected $packageForUpdateOutput = 'eirik/private-pack';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.0.2';
    protected $calledCorrectly = false;
    protected $composerAssetFiles = 'composer-json-private';

    public function testIssue98()
    {
        self::assertEquals(false, $this->calledCorrectly);
        $this->placeComposerLockContentsFromFixture('composer-lock-private.lock', $this->dir);
        $this->runtestExpectedOutput();
        $this->assertEquals($this->calledCorrectly, $this->calledCorrectly);
    }

    protected function handleExecutorReturnCallback($cmd, &$return)
    {
        if ($cmd == $this->createExpectedCommandForPackage('eirik/private-pack')) {
            $this->placeComposerLockContentsFromFixture('composer-lock-private.updated', $this->dir);
        }
        if ($cmd === ["git", "clone", 'https://user-token:x-oauth-basic@github.com/eiriksm/private-pack.git', '/tmp/9f7527992e178cafad06d558b8f32ce8']) {
            $this->calledCorrectly = true;
        }
    }
}
