<?php

namespace eiriksm\CosyComposerTest\integration;

use GuzzleHttp\Psr7\Response;
use Http\Client\HttpClient;

class OtherMinorBranchDrupalSecTest extends DrupalRuntimeSecUpdateTest
{

    public function setUp()
    {
        // We want to switch the http client so we can return the cached XML we know from a specific date.
        parent::setUp();
        $mock_response = new Response(200, [], file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'updates-09-09-2021.xml'));
        $client = $this->createMock(HttpClient::class);
        $client->method('sendRequest')
            ->willReturn($mock_response);
        $this->cosy->setHttpClient($client);
    }

    public function getDrupalUpdatesAndSec()
    {
        return [
            [
                // Last current version of last minor branch.
                '9.1.12',
                'drupal/core',
                false,
            ],
            [
                // Last current version.
                '9.2.5',
                'drupal/core',
                false,
            ],
            [
                // Last current branch version which was insecure.
                '9.2.3',
                'drupal/core',
                true,
            ],
            // Some other version that were insecure at that point in time.
            [
                '9.1.11',
                'drupal/core',
                true,
            ],
            [
                '8.9.17',
                'drupal/core',
                true,
            ],
            [
                // Last in the 9.0 series. Currently unsupported branch
                '9.0.14',
                'drupal/core',
                true,
            ],
            [
                // Last in the 8.9 series. Currently supported.
                '8.9.18',
                'drupal/core',
                false,
            ],
            [
                // Last in the 8.8 series. Currently unsupported.
                '8.8.12',
                'drupal/core',
                true,
            ],
        ];
    }
}
