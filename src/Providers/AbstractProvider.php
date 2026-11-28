<?php

namespace eiriksm\CosyComposer\Providers;

use eiriksm\CosyComposer\ProviderInterface;
use Violinist\Slug\Slug;

abstract class AbstractProvider implements ProviderInterface
{
    protected const REPO_CACHE_KEY = 'repo';
    protected const BRANCHES_CACHE_KEY = 'branches';
    protected const AUTHENTICATED_USERNAME_CACHE_KEY = 'authenticated_username';

    /**
     * Results of api lookups, keyed by what was looked up.
     *
     * @var array<string, mixed>
     */
    protected array $cache = [];

    /**
     * Return the cached result for a key, or look it up and cache it.
     *
     * The result is cached even when it is null, so a lookup runs at most once.
     * If the lookup throws, nothing is cached and the exception is passed on.
     *
     * @return mixed
     */
    protected function wrapLookupInCache(string $key, callable $fallback_data_lookup)
    {
        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $fallback_data_lookup();
        }
        return $this->cache[$key];
    }

    /**
     * Build a cache key for data that belongs to a single repo.
     */
    protected function getRepoCacheKey(string $key, Slug $slug) : string
    {
        return sprintf('%s:%s', $key, $this->getRepoIdentifier($slug));
    }

    /**
     * The identifier the provider api uses for the repo of a slug.
     */
    protected function getRepoIdentifier(Slug $slug) : string
    {
        return sprintf('%s/%s', $slug->getUserName(), $slug->getUserRepo());
    }

    /**
     * Get the repo data from the provider api.
     *
     * @return array<mixed>
     */
    abstract protected function getRepo(Slug $slug) : array;
}
