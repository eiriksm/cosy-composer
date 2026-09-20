<?php

namespace eiriksm\CosyComposerTest\integration;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Http\Client\HttpClient;
use Violinist\SymfonyCloudSecurityChecker\SecurityChecker;

class UpdateBranchTitleChangedPopulateTest extends UpdateBranchTitleChangedTest
{

    protected ?string $composerAssetFiles = 'composer.drupal1021';
    protected ?string $packageForUpdateOutput = 'drupal/core-recommended';
    protected ?string $packageVersionForFromUpdateOutput = '10.2.1';
    protected ?string $packageVersionForToUpdateOutput = '10.2.2';

    public function setUp() : void
    {
        parent::setUp();
        $response = $this->createMock(Response::class);
        $stream = Utils::streamFor('<?xml version="1.0" encoding="utf-8"?>
<project xmlns:dc="http://purl.org/dc/elements/1.1/"><releases></releases></project>');
        $response->method('getBody')
            ->willReturn($stream);
        $client = $this->createMock(HttpClient::class);
        $client->method('sendRequest')
            ->willReturn($response);
        $this->cosy->setHttpClient($client);
        $checker = $this->createMock(SecurityChecker::class);
        $checker->method('checkDirectory')
            ->willReturn([
                'drupal/core' => true,
            ]);
        $this->cosy->getCheckerFactory()->setChecker($checker);
    }

    public function testSecUpdateUpdatesBranchPopulateBasedOnDrupal()
    {
        $this->runtestExpectedOutput();
        $this->assertOutputContainsMessage('Updating the PR of drupal/core-recommended since the computed title does not match the title.', $this->cosy);
    }
}
