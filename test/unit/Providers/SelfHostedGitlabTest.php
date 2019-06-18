<?php

namespace eiriksm\CosyComposerTest\unit\Providers;

use eiriksm\CosyComposer\Providers\Gitlab;
use eiriksm\CosyComposer\Providers\SelfHostedGitlab;
use Gitlab\Api\MergeRequests;
use Gitlab\Api\Projects;
use Gitlab\Api\Repositories;
use Gitlab\Client;

class SelfHostedGitlabTest extends GitlabProviderTest
{
    public function getProvider($client)
    {
        $mock_url = parse_url('http://example.com:80/user/repo');
        return new SelfHostedGitlab($client, $mock_url);
    }
}
