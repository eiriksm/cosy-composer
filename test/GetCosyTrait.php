<?php

namespace eiriksm\CosyComposerTest;

use eiriksm\CosyComposer\CommandExecuter;
use eiriksm\CosyComposer\CosyComposer;
use eiriksm\CosyComposer\ProviderFactory;
use eiriksm\CosyComposer\ProviderInterface;
use GuzzleHttp\Psr7\Response;
use Http\Client\HttpClient;
use Violinist\ProjectData\ProjectData;
use Violinist\SymfonyCloudSecurityChecker\SecurityChecker;

trait GetCosyTrait
{
    protected function getMockCosy(?string $dir = null) : CosyComposer
    {
        $executer = $this->createMock(CommandExecuter::class);
        $c = new CosyComposer($executer);
        $c->setUrl('https://github.com/a/b');
        $p = new ProjectData();
        $p->setNid(123);
        $c->setProject($p);
        $c->setTokenUrl('http://localhost:9988');
        if ($dir) {
            mkdir($dir);
            $c->setTmpDir($dir);
        }
        $mock_checker = $this->createMock(SecurityChecker::class);
        $c->getCheckerFactory()->setChecker($mock_checker);
        $c->setAuthentication('user-token');
        $fixturesDir = __DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR;
        $emptyXml = '<?xml version="1.0" encoding="utf-8"?>
<project xmlns:dc="http://purl.org/dc/elements/1.1/"><releases></releases></project>';
        $client = $this->createMock(HttpClient::class);
        $client->method('sendRequest')
            ->willReturnCallback(function ($request) use ($fixturesDir, $emptyXml) {
                $url = (string) $request->getUri();
                if (str_contains($url, '/7.x')) {
                    $xml = @file_get_contents($fixturesDir . 'updates-drupal-7x.xml');
                } elseif (str_contains($url, '/8.x')) {
                    $xml = @file_get_contents($fixturesDir . 'updates-drupal-8x.xml');
                } elseif (str_contains($url, '/current')) {
                    $xml = @file_get_contents($fixturesDir . 'updates-drupal-current.xml');
                } else {
                    $xml = false;
                }
                return new Response(200, [], $xml ?: $emptyXml);
            });
        $c->setHttpClient($client);
        $mock_client = $this->createMock(ProviderInterface::class);
        $mock_provider_factory = $this->createMock(ProviderFactory::class);
        $mock_provider_factory->method('createFromHost')
            ->willReturn($mock_client);
        $c->setProviderFactory($mock_provider_factory);
        // We don't strictly need this, but it's nice for coverage to always
        // cover this code branch.
        $c->setViolinistHostname('violinist-test-runner');
        return $c;
    }
}
