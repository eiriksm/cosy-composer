<?php

namespace eiriksm\CosyComposerTest\unit\Providers;

use eiriksm\CosyComposer\ProviderInterface;
use PHPUnit\Framework\MockObject\MockObject;

interface TestProviderInterface
{
    /**
     * @return MockObject
     */
    public function getMockClient();

    /**
     * @param MockObject $client
     * @return ProviderInterface
     */
    public function getProvider($client);

    /**
     * @return string
     */
    public function getBranchMethod();
}
