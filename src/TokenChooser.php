<?php

namespace eiriksm\CosyComposer;

class TokenChooser
{
    private $url;
    private $token;
    private $otherTokens = [];

    public function __construct(string $url, string $token = null, array $other_tokens = [])
    {
        $this->url = $url;
        $this->token = $token;
        $this->otherTokens = $other_tokens;
    }

    public function setUserToken(string $untouchedUserToken)
    {
        $this->token = $untouchedUserToken;
    }

    public function addTokens(array $tokens)
    {
        $this->otherTokens = array_merge($this->otherTokens, $tokens);
    }

    public function getChosenToken(string $repository_url)
    {
        if (empty($this->token) && empty($this->otherTokens)) {
            return null;
        }
        if (empty($this->otherTokens)) {
            return $this->token;
        }
        // Now first check if the url of the object matches the repository url.
        $hostname = parse_url($this->url, PHP_URL_HOST);
        // We don't want www. in the hostname if it's one of the three big VCS
        // hosts. So basically, if it matches one of them + has wwww. in front,
        // remove the www part.
        if (strpos($hostname, 'www.') === 0) {
            // But only if one of those leading VCS provider hostnames.
            if (in_array($hostname, ['www.github.com', 'www.gitlab.com', 'www.bitbucket.com'])) {
                $hostname = str_replace('www.', '', $hostname);
            }
        }
        if (empty($hostname)) {
            return $this->token ?? null;
        }
        if (!empty($this->token) && strpos($repository_url, $hostname) !== false) {
            return $this->token;
        }
        // Now see if any of the other tokens match.
        foreach ($this->otherTokens as $other_token) {
            if (strpos($repository_url, $hostname) !== false) {
                return $other_token;
            }
        }
        return $this->token ?? null;
    }
}
