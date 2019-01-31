<?php

namespace eiriksm\CosyComposerTest\unit\Providers;

use eiriksm\CosyComposer\Providers\Gitlab;
use Gitlab\Api\MergeRequests;
use Gitlab\Api\Projects;
use Gitlab\Api\Repositories;
use Gitlab\Client;

class GitlabProviderTest extends ProvidersTestBase
{
    protected $authenticateArguments = [
        'testUser',
        Client::AUTH_OAUTH_TOKEN
    ];

    protected $authenticatePrivateArguments = [
        'testUser',
        Client::AUTH_OAUTH_TOKEN
    ];

    public function testRepoIsPrivate()
    {
        $client = $this->getMockClient();
        $provider = $this->getProvider($client);
        $this->assertEquals(true, $provider->repoIsPrivate('testUser', 'testRepo'));
    }

    protected function getProvider(Client $client)
    {
        return new Gitlab($client);
    }

    protected function getMockClient()
    {
        return $this->createMock(Client::class);
    }

    protected function getBranchMethod()
    {
        return 'projects';
    }

    protected function getRepoClassName($context)
    {
        if ($context == 'branches') {
            return Repositories::class;
        }
        return Projects::class;
    }

    protected function getPrClassName()
    {
        return MergeRequests::class;
    }

    protected function getPrApiMethod()
    {
        return 'mr';
    }
}
