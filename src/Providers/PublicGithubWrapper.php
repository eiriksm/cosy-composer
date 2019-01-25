<?php

namespace eiriksm\CosyComposer\Providers;

use Github\Exception\ValidationFailedException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\ServerRequest;
use function GuzzleHttp\Psr7\stream_for;
use Http\Adapter\Guzzle6\Client;
use Http\Client\Common\Plugin\CookiePlugin;
use Http\Client\Common\PluginClient;
use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Message\Cookie;
use Http\Message\CookieJar;
use Http\Message\MessageFactory;
use Violinist\ProjectData\ProjectData;

class PublicGithubWrapper extends Github
{
    /**
     * @var string
     */
    private $userToken;

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var ProjectData
     */
    private $project;

    private $httpClient;

    /**
     * @param string $userToken
     */
    public function setUserToken($userToken)
    {
        $this->userToken = $userToken;
    }

    public function setUrlFromTokenUrl($url)
    {
        $parsed_url = parse_url($url);
        $this->baseUrl = sprintf('%s://%s', $parsed_url['scheme'], $parsed_url['host']);
    }

    /**
     * @param ProjectData $project
     */
    public function setProject($project)
    {
        $this->project = $project;
    }

    public function forceUpdateBranch($branch, $sha)
    {
        $jar = new CookieJar();
        $jar->addCookie(new Cookie('XDEBUG_SESSION', 'XDEBUG_ECLIPSE', null, 'violinist.localhost'));
        $plugin = new CookiePlugin($jar);
        $client = new PluginClient(HttpClientDiscovery::find(), [$plugin]);
        $url = sprintf('%s/api/github/update_branch?nid=%d&token=%s&branch=%s&new_sha=%s', $this->baseUrl, $this->project->getNid(), $this->userToken, $branch, $sha);
        $request = new Request('GET', $url);
        $resp = $client->sendRequest($request);
        if ($resp->getStatusCode() != 200) {
            throw new \Exception('Wrong status code on update branch request (' . $resp->getStatusCode() . ').');
        }
        if (!$json = @json_decode((string) $resp->getBody())) {
            throw new \Exception('No json parsed in update branch response');
        }
    }

    public function createFork($user, $repo, $fork_user)
    {
        // Send all this data to the website endpoint.
        $jar = new CookieJar();
        $jar->addCookie(new Cookie('XDEBUG_SESSION', 'XDEBUG_ECLIPSE', null, 'violinist.localhost'));
        $plugin = new CookiePlugin($jar);
        $client = new PluginClient(HttpClientDiscovery::find(), [$plugin]);
        $request = new Request('GET', $this->baseUrl . '/api/github/create_fork?nid=' . $this->project->getNid() . '&token=' . $this->userToken);
        $resp = $client->sendRequest($request);
        if ($resp->getStatusCode() != 200) {
            throw new \Exception('Wrong status code on create fork request (' . $resp->getStatusCode() . ').');
        }
        if (!$json = @json_decode((string) $resp->getBody())) {
            throw new \Exception('No json parsed in the create fork response');
        }
    }

    public function createPullRequest($user_name, $user_repo, $params)
    {
        $http_client = $this->getHttpClient();
        $request = $this->createPullRequestRequest($user_name, $user_repo, $params);
        $jar = new CookieJar();
        $jar->addCookie(new Cookie('XDEBUG_SESSION', 'XDEBUG_ECLIPSE', null, 'violinist.localhost'));
        $plugin = new CookiePlugin($jar);
        $client = new PluginClient(HttpClientDiscovery::find(), [$plugin]);
        $resp = $client->sendRequest($request);
        if ($resp->getStatusCode() == 422) {
            throw new ValidationFailedException();
        }
        if ($resp->getStatusCode() != 200) {
            throw new \Exception('Wrong status code on create PR request (' . $resp->getStatusCode() . ').');
        }
        if (!$json = @json_decode((string) $resp->getBody())) {
            throw new \Exception('No json parsed in the create PR response');
        }
        return (array) $json;
    }

    public function updatePullRequest($user_name, $user_repo, $id, $params)
    {
        $http_client = $this->getHttpClient();
        $jar = new CookieJar();
        $jar->addCookie(new Cookie('XDEBUG_SESSION', 'XDEBUG_ECLIPSE', null, 'violinist.localhost'));
        $plugin = new CookiePlugin($jar);
        $client = new PluginClient(HttpClientDiscovery::find(), [$plugin]);
        $params['id'] = $id;
        $request = $this->createPullRequestRequest($user_name, $user_repo, $params, 'update_pr');
        $resp = $client->sendRequest($request);
        if ($resp->getStatusCode() != 200) {
            throw new \Exception('Wrong status code on create PR request (' . $resp->getStatusCode() . ').');
        }
        if (!$json = @json_decode((string) $resp->getBody())) {
            throw new \Exception('No json parsed in the create PR response');
        }
    }

    protected function createPullRequestRequest($user_name, $user_repo, $params, $path = 'create_pr')
    {
        $data = array_merge($params, [
            'nid' => $this->project->getNid(),
            'token' => $this->userToken,
            'user_name' => $user_name,
            'user_repo' => $user_repo,
        ]);
        $request = new Request('POST', $this->baseUrl . '/api/github/' . $path, [
            'Content-type' => 'application/json',
            'Accept' => 'application/json',
        ]);
        $request = $request->withBody(stream_for(json_encode($data)));
        return $request;
    }

    public function commitNewFiles($tmp_dir, $sha, $branch, $message)
    {
        // Get the contents of all composer related files.
        $files = [
            'composer.lock',
            'composer.json',
        ];
        $files_with_contents = [];
        foreach ($files as $file) {
            $filename = "$tmp_dir/$file";
            if (!file_exists($filename)) {
                continue;
            }
            $files_with_contents[$file] = file_get_contents($filename);
        }
        $data = [
            'nid' => $this->project->getNid(),
            'token' => $this->userToken,
            'files' => $files_with_contents,
            'sha' => $sha,
            'branch' => $branch,
            'message' => $message,
        ];
        $jar = new CookieJar();
        $jar->addCookie(new Cookie('XDEBUG_SESSION', 'XDEBUG_ECLIPSE', null, 'violinist.localhost'));
        $plugin = new CookiePlugin($jar);
        $client = new PluginClient(HttpClientDiscovery::find(), [$plugin]);
        $request = new Request('POST', $this->baseUrl . '/api/github/create_commit', [
            'Content-type' => 'application/json',
            'Accept' => 'application/json',
        ]);
        $request = $request->withBody(stream_for(json_encode($data)));
        $client->sendRequest($request);
    }

    protected function getHttpClient()
    {
        if (!$this->httpClient) {
            $this->httpClient = new Client();
        }
        return $this->httpClient;
    }
}
