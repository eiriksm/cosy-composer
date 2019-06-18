<?php

namespace eiriksm\CosyComposerTest\integration\issues;

use eiriksm\CosyComposer\CommandExecuter;
use eiriksm\CosyComposerTest\integration\Base;

/**
 * Class Issue98Test.
 *
 * Issue 98 was that after we switched the change log fetcher, we forgot to set the auth on the fetcher, so private
 * repos were not fetched with auth tokens set.
 */
class Issue98Test extends Base
{
    public function testIssue98()
    {
        $c = $this->cosy;
        $dir = $this->dir;
        $this->getMockOutputWithUpdate('eirik/private-pack', '1.0.0', '1.0.2');
        $this->placeComposerContentsFromFixture('composer-json-private.json', $dir);
        $mock_executer = $this->createMock(CommandExecuter::class);
        $called_dependency_clone_correctly = false;
        $mock_executer->method('executeCommand')
            ->will($this->returnCallback(
                function ($cmd) use ($dir, &$called_dependency_clone_correctly) {
                    if ($cmd == $this->createExpectedCommandForPackage('eirik/private-pack')) {
                        $this->placeComposerLockContentsFromFixture('composer-lock-private.updated', $dir);
                    }
                    if ($cmd === "git clone https://user-token:x-oauth-basic@github.com/eiriksm/private-pack.git /tmp/9f7527992e178cafad06d558b8f32ce8") {
                        $called_dependency_clone_correctly = true;
                    }
                    return 0;
                }
            ));
        $c->setExecuter($mock_executer);
        $this->assertEquals(false, $called_dependency_clone_correctly);
        $this->registerProviderFactory($c);
        $this->placeComposerLockContentsFromFixture('composer-lock-private.lock', $dir);
        $c->run();
        $this->assertEquals(true, $called_dependency_clone_correctly);
    }
}
