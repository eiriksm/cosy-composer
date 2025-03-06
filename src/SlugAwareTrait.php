<?php

namespace eiriksm\CosyComposer;

use Violinist\Slug\Slug;

trait SlugAwareTrait
{
    /**
     * @var Slug
     */
    protected $slug;

    public function setSlug(Slug $slug)
    {
        $this->slug = $slug;
    }

    public function getSlug() : ?Slug
    {
        return $this->slug;
    }
}
