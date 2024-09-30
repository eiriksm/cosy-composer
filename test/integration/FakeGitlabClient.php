<?php

namespace eiriksm\CosyComposerTest\integration;

use Gitlab\Api\MergeRequests;
use Gitlab\Client;

class FakeGitlabClient extends Client
{

    protected $calls = [];

    public function appendCall($call)
    {
        $this->calls[] = $call;
    }

    public function getCalls()
    {
        return $this->calls;
    }

    public function mergeRequests() : MergeRequests
    {
        return (new class($this) extends MergeRequests {

            private $client;

            public function __construct(Client $client, ?int $perPage = null)
            {
                parent::__construct($client, $perPage);
                $this->client = $client;
            }

            public function update($project_id, $mr_id, $params)
            {
                $this->client->appendCall([
                    'MergeRequests',
                    'update',
                    $project_id,
                    $mr_id,
                    $params,
                ]);
            }
        });
    }
}
