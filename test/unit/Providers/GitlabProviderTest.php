<?php

namespace eiriksm\CosyComposerTest\unit\Providers;

use eiriksm\CosyComposer\Providers\Gitlab;
use Gitlab\Api\MergeRequests;
use Gitlab\Api\Projects;
use Gitlab\Api\Repositories;
use Gitlab\Client;
use Violinist\Slug\Slug;

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
        $slug = Slug::createFromUrl('http://gitlab.com/testUser/testRepo');
        $client = $this->getMockClient();
        $provider = $this->getProvider($client);
        $this->assertEquals(true, $provider->repoIsPrivate($slug));
    }

    public function getProvider($client)
    {
        return new Gitlab($client);
    }

    public function getMockClient()
    {
        return $this->createMock(Client::class);
    }

    public function getBranchMethod()
    {
        return 'projects';
    }

    protected function getRepoClassName($context)
    {
        if ($context === 'branches') {
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
