<?php

namespace eiriksm\CosyComposer;

use eiriksm\CosyComposer\Providers\Bitbucket;
use eiriksm\CosyComposer\Providers\Github;
use eiriksm\CosyComposer\Providers\Gitlab;
use eiriksm\CosyComposer\Providers\SelfHostedGitlab;
use Github\Client;
use Violinist\Slug\Slug;

class ProviderFactory
{
    public function createFromHost(Slug $slug, $url)
    {
        $host = $slug->getProvider();
        $provider = null;
        switch ($host) {
            case 'github.com':
                $client = new Client();
                $provider = new Github($client);
                break;

            case 'gitlab.com':
                $client = new \Gitlab\Client();
                $provider = new Gitlab($client);
                break;

            case 'bitbucket.org':
                $client = new \Bitbucket\Client();
                $provider = new Bitbucket($client);
                break;

            default:
                // @todo: Support more self-hosted at some point.
                $client = new \Gitlab\Client();
                $provider = new SelfHostedGitlab($client, $url);
                break;
        }
        return $provider;
    }
}
