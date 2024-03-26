<?php

namespace eiriksm\CosyComposerTest;

use Composer\Console\Application;
use eiriksm\ArrayOutput\ArrayOutput;
use eiriksm\CosyComposer\CommandExecuter;
use eiriksm\CosyComposer\CosyComposer;
use eiriksm\CosyComposer\ProviderFactory;
use eiriksm\CosyComposer\ProviderInterface;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Http\Adapter\Guzzle7\Client;
use Http\Client\HttpClient;
use Violinist\ProjectData\ProjectData;
use Violinist\SymfonyCloudSecurityChecker\SecurityChecker;

trait GetCosyTrait
{
    protected function getMockCosy($dir = null)
    {
        $executer = $this->createMock(CommandExecuter::class);
        $c = new CosyComposer($executer);
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
        $c->setUserToken('user-token');
        $response = $this->createMock(Response::class);
        $stream = Utils::streamFor('<?xml version="1.0" encoding="utf-8"?>
<project xmlns:dc="http://purl.org/dc/elements/1.1/"><releases></releases></project>');
        $response->method('getBody')
            ->willReturn($stream);
        $client = $this->createMock(HttpClient::class);
        $client->method('sendRequest')
            ->willReturn($response);
        $c->setHttpClient($client);
        $mock_client = $this->createMock(ProviderInterface::class);
        $mock_provider_factory = $this->createMock(ProviderFactory::class);
        $mock_provider_factory->method('createFromHost')
            ->willReturn($mock_client);
        $c->setProviderFactory($mock_provider_factory);
        return $c;
    }
}
