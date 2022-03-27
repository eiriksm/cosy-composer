<?php

namespace eiriksm\CosyComposerTest\integration;

use Violinist\Slug\Slug;
use Violinist\SymfonyCloudSecurityChecker\SecurityChecker;

class UpdateAllTest extends UpdateAllBase
{

    public function testUpdateAllPlain()
    {
        $this->cosy->run();
        self::assertEquals($this->foundCommand, true);
        self::assertEquals($this->foundCommit, true);
        self::assertEquals($this->foundBranch, true);
    }

    public function testUpdateAllSecurity()
    {
        $checker = $this->createMock(SecurityChecker::class);
        $checker->method('checkDirectory')
            ->willReturn([
                'psr/log' => true,
            ]);
        $has_security_title = false;
        $this->cosy->getCheckerFactory()->setChecker($checker);
        $this->getMockProvider()->method('createPullRequest')
            ->willReturnCallback(function (Slug $slug, array $params) use (&$has_security_title) {
                if ($params["title"] === '[SECURITY] Update all composer dependencies') {
                    $has_security_title = true;
                }
                return [
                    'html_url' => 'http://example.com/my/pr/1',
                ];
            });
        $this->testUpdateAllPlain();
        self::assertTrue($has_security_title);
    }
}
