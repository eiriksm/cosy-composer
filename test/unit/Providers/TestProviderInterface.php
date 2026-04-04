<?php

namespace eiriksm\CosyComposerTest\unit\Providers;

interface TestProviderInterface
{
    /**
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    public function getMockClient();

    /**
     * @param \PHPUnit\Framework\MockObject\MockObject $client
     * @return \eiriksm\CosyComposer\ProviderInterface
     */
    public function getProvider($client);

    /**
     * @return string
     */
    public function getBranchMethod();
}
