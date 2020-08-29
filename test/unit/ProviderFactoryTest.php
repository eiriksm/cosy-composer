<?php

namespace eiriksm\CosyComposerTest\unit;

use eiriksm\CosyComposer\ProviderFactory;
use eiriksm\CosyComposer\Providers\Bitbucket;
use eiriksm\CosyComposer\Providers\Github;
use eiriksm\CosyComposer\Providers\Gitlab;
use eiriksm\CosyComposer\Providers\SelfHostedGitlab;
use PHPUnit\Framework\TestCase;
use Violinist\Slug\Slug;

class ProviderFactoryTest extends TestCase
{
    /**
     * @dataProvider getHostAndClass
     */
    public function testCreateFromHost($url, $class)
    {
        if ($url === 'https://bitbucket.org/eiriksm/cosy-composer' && version_compare(phpversion(), "7.1.0", "<=")) {
            $this->assertTrue(true, 'Skipping bitbucket test for version ' . phpversion());
            return;
        }
        $pf = new ProviderFactory();
        $slug = Slug::createFromUrl($url);
        $url_array = parse_url($url);
        $url_array['port'] = 443;
        $provider = $pf->createFromHost($slug, $url_array);
        $this->assertEquals(get_class($provider), $class);
    }

    public function getHostAndClass()
    {
        return [
            [
                'https://github.com/eiriksm/cosy-composer',
                Github::class
            ],
            [
                'https://gitlab.com/eiriksm/cosy-composer',
                Gitlab::class
            ],
            [
                'https://bitbucket.org/eiriksm/cosy-composer',
                Bitbucket::class
            ],
            [
                'https://example.com/eiriksm/cosy-composer',
                SelfHostedGitlab::class
            ],
        ];
    }
}
