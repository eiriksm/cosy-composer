<?php

namespace eiriksm\CosyComposerTest\unit\Providers;

interface TestProviderInterface
{
    /**
     * @return object
     */
    public function getMockClient();

    /**
     * @return \eiriksm\CosyComposer\ProviderInterface
     */
    public function getProvider($client);

    /**
     * @return string
     */
    public function getBranchMethod();
}
