<?php

namespace eiriksm\CosyComposerTest;

use Composer\Console\Application;
use eiriksm\ArrayOutput\ArrayOutput;
use eiriksm\CosyComposer\CommandExecuter;
use eiriksm\CosyComposer\CosyComposer;
use GuzzleHttp\Psr7\Response;
use Http\Adapter\Guzzle6\Client;
use Violinist\ProjectData\ProjectData;
use Violinist\SymfonyCloudSecurityChecker\SecurityChecker;

trait GetCosyTrait
{
    protected function getMockCosy($dir = null)
    {
        $app = $this->createMock(Application::class);
        $output = $this->createMock(ArrayOutput::class);
        $executer = $this->createMock(CommandExecuter::class);
        $c = new CosyComposer('a/b', $app, $output, $executer);
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
        $response->method('getBody')
            ->willReturn('<?xml version="1.0" encoding="utf-8"?>
<project xmlns:dc="http://purl.org/dc/elements/1.1/"><releases></releases></project>');
        $client = $this->createMock(Client::class);
        $client->method('sendRequest')
            ->willReturn($response);
        $c->setHttpClient($client);
        return $c;
    }
}
