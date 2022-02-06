<?php

namespace eiriksm\CosyComposerTest\integration;

use Github\Exception\ValidationFailedException;
use Gitlab\Exception\RuntimeException;
use Violinist\ProjectData\ProjectData;
use Violinist\Slug\Slug;
use Violinist\SymfonyCloudSecurityChecker\SecurityChecker;

class UpdateAllUpdatePrTest extends UpdateAllBase
{
    protected $composerJson = 'composer.allow_all_assignees.json';
    protected $prParams = [];

    /**
     * @dataProvider exceptionDataProvider
     */
    public function testUpdateAllUpdatePR($exception_class)
    {
        $project = new ProjectData();
        $project->setRoles(['agency']);
        $this->cosy->setProject($project);
        $this->getMockProvider()->method('createPullRequest')
            ->willReturnCallback(function (Slug $slug, array $params) use (&$has_security_title) {
                if ($params["title"] === '[SECURITY] Update all composer dependencies') {
                    $has_security_title = true;
                }
                return [
                    'html_url' => 'http://example.com/my/pr/1',
                ];
            });
        $update_called = false;
        $this->getMockProvider()->method('createPullRequest')
            ->willReturnCallback(function ($slug, $pr_params) use ($exception_class) {
                $this->prParams = $pr_params;
                throw new $exception_class('We are faking a PR exists');
            });
        $this->getMockProvider()->method('updatePullRequest')
            ->willReturnCallback(function (Slug $slug, $id, $params) use (&$update_called) {
                $this->prParams = $params;
                $update_called = true;
            });
        $this->cosy->run();
        self::assertTrue($update_called);
        self::assertEquals($this->foundCommand, true);
        self::assertEquals($this->foundCommit, true);
        self::assertEquals($this->foundBranch, true);
        self::assertNotEmpty($this->prParams["assignees"]);
    }

    public function exceptionDataProvider()
    {
        return [
            [
                ValidationFailedException::class,
            ],
            [
                RuntimeException::class,
            ]
        ];
    }

    protected function getPrsNamed()
    {
        return [
            'violinistall' => [
                'number' => 123,
                'title' => 'Update all composer dependencies',
                'body' => 'Totally not the same body',
            ],
        ];
    }
}
