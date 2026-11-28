<?php

namespace eiriksm\CosyComposer\Providers;

use eiriksm\CosyComposer\ProviderInterface;
use Violinist\Slug\Slug;

abstract class AbstractProvider implements ProviderInterface
{
    protected array $cache = [];

    public function wrapLookupInCache(string $key, callable $fallback_data_lookup) : array
    {
        if (!isset($this->cache[$key])) {
            $this->cache[$key] = $fallback_data_lookup();
        }
        return $this->cache[$key];
    }

    abstract protected function getRepo(Slug $slug);
}
