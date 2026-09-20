<?php

namespace eiriksm\CosyComposerTest\unit\Providers;

use eiriksm\CosyComposer\ProviderInterface;

interface TestProviderInterface
{
    /**
     * @return object
     */
    public function getMockClient();

    public function getProvider(object $client) : ProviderInterface;
}
