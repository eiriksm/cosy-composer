<?php

namespace eiriksm\CosyComposer\Providers;

use Github\Exception\ValidationFailedException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\ServerRequest;
use Http\Client\Common\Plugin;
use Http\Client\HttpClient;
use Psr\Http\Message\ResponseInterface;
use Violinist\Slug\Slug;
use GuzzleHttp\Psr7\Utils;
use Http\Adapter\Guzzle7\Client;
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
        $plugin = new CookiePlugin($jar);
        $client = $this->getPluginClient($plugin);
        $url = sprintf('%s/api/github/update_branch?nid=%d&token=%s&branch=%s&new_sha=%s', $this->baseUrl, $this->project->getNid(), $this->userToken, $branch, $sha);
        $request = new Request('GET', $url);
        $resp = $client->sendRequest($request);
        $this->handleStatusCodeAndJsonResponse($resp, 'update branch');
    }

    public function createFork($user, $repo, $fork_user)
    {
        // Send all this data to the website endpoint.
        $jar = new CookieJar();
        $plugin = new CookiePlugin($jar);
        $client = $this->getPluginClient($plugin);
        $request = new Request('GET', $this->baseUrl . '/api/github/create_fork?nid=' . $this->project->getNid() . '&token=' . $this->userToken);
        $resp = $client->sendRequest($request);
        $this->handleStatusCodeAndJsonResponse($resp, 'create fork');
    }

    protected function handleStatusCodeAndJsonResponse(ResponseInterface $response, string $response_name)
    {
        if ($response->getStatusCode() != 200) {
            throw new \Exception(sprintf('Wrong status code on %s request (%d)', $response_name, $response->getStatusCode()));
        }
        if (!$json = @json_decode((string) $response->getBody())) {
            throw new \Exception(sprintf('No json parsed in the %s response', $response_name));
        }
        return $json;
    }

    public function createPullRequest(Slug $slug, $params)
    {
        $user_name = $slug->getUserName();
        $user_repo = $slug->getUserRepo();
        $request = $this->createPullRequestRequest($user_name, $user_repo, $params);
        $jar = new CookieJar();
        $plugin = new CookiePlugin($jar);
        $client = $this->getPluginClient($plugin);
        $resp = $client->sendRequest($request);
        if ($resp->getStatusCode() == 422) {
            $msg = 'Remote create PR request failed';
            if ($json = @json_decode($resp->getBody())) {
                if (!empty($json->error) && is_string($json->error)) {
                    $msg = $json->error;
                }
            }
            throw new ValidationFailedException($msg);
        }
        $json = $this->handleStatusCodeAndJsonResponse($resp, 'create PR');
        return (array) $json;
    }

    public function updatePullRequest(Slug $slug, $id, $params)
    {
        $user_name = $slug->getUserName();
        $user_repo = $slug->getUserRepo();
        $jar = new CookieJar();
        $plugin = new CookiePlugin($jar);
        $client = $this->getPluginClient($plugin);
        $params['id'] = $id;
        $request = $this->createPullRequestRequest($user_name, $user_repo, $params, 'update_pr');
        $resp = $client->sendRequest($request);
        $this->handleStatusCodeAndJsonResponse($resp, 'update PR');
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
        $request = $request->withBody(Utils::streamFor(json_encode($data)));
        return $request;
    }

    public function commitNewFiles($tmp_dir, $sha, $branch, $message, $lock_file_contents)
    {
        // Get the contents of all composer related files.
        $files = [
            'composer.json',
        ];
        if ($lock_file_contents) {
            $files[] = 'composer.lock';
        }
        $files_with_contents = [];
        foreach ($files as $file) {
            $subdir = '';
            if ($this->project->getComposerJsonDir()) {
                $subdir = $this->project->getComposerJsonDir() . '/';
            }
            $filename = "$tmp_dir/$subdir$file";
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
        $plugin = new CookiePlugin($jar);
        $client = $this->getPluginClient($plugin);
        $request = new Request('POST', $this->baseUrl . '/api/github/create_commit', [
            'Content-type' => 'application/json',
            'Accept' => 'application/json',
        ]);
        $request = $request->withBody(Utils::streamFor(json_encode($data)));
        $response = $client->sendRequest($request);
        $this->handleStatusCodeAndJsonResponse($response, 'commit files');
    }

    protected function getPluginClient(Plugin $plugin)
    {
        return new PluginClient($this->getHttpClient(), [$plugin]);
    }

    public function setHttpClient(HttpClient $client)
    {
        $this->httpClient = $client;
    }

    protected function getHttpClient()
    {
        if (!$this->httpClient) {
            $this->httpClient = HttpClientDiscovery::find();
        }
        return $this->httpClient;
    }
}
