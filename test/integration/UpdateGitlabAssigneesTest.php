<?php

namespace eiriksm\CosyComposerTest\integration;

use Gitlab\Client;
use Violinist\ProjectData\ProjectData;

class UpdateGitlabAssigneesTest extends UpdateExistingWithAssigneesTest
{

    public function setUp(): void
    {
        parent::setUp();
     }

    protected function getMockProvider()
    {
        if (!$this->mockProvider) {
            $this->mockProvider = new FakeGitlab(new FakeGitlabClient());
        }
        return $this->mockProvider;
    }

    protected function setDummyGithubProvider()
    {
        $mock_provider_factory = $this->getMockProviderFactory();
        $mock_provider_factory->method('createFromHost')
            ->willReturn($this->getMockProvider());

        $this->cosy->setProviderFactory($mock_provider_factory);
    }

    /**
     * @dataProvider exceptionDataProvider
     */
    public function testPrUpdatedWhenConflict($exception_class)
    {
        $project = new ProjectData();
        $project->setRoles(['agency']);
        $this->cosy->setProject($project);
        $this->cosy->setUrl('https://gitlab.com/a/b');
        $this->runtestExpectedOutput();
        $calls = $this->mockProvider->getClient()->getCalls();
        foreach ($calls as $call) {
            if ($call[0] !== 'MergeRequests' || $call[1] !== 'update') {
                continue;
            }
            if (empty($call[4]['assignee_ids']) && empty($call[4]['assignee_id'])) {
                $this->fail('Assignee ids not set');
            }
        }
    }
}