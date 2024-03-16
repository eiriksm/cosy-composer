<?php

namespace eiriksm\CosyComposerTest\unit\Providers;

use eiriksm\CosyComposer\Providers\SelfHostedGitlab;

class SelfHostedGitlabTest extends GitlabProviderTest
{
    public function getProvider($client)
    {
        $mock_url = parse_url('http://example.com:80/user/repo');
        return new SelfHostedGitlab($client, $mock_url);
    }
}
