<?php

namespace eiriksm\CosyComposer;

use Violinist\Config\Config;

trait ConfigOverrideLoggerTrait
{
    protected function logConfigOverride(Config $config, string $optionKey): void
    {
        $extends = $config->getExtendNameForKey($optionKey);
        if (!$extends) {
            return;
        }
        $this->log(sprintf('The config option %s was set by the extends config %s', $optionKey, $extends));
        $chain = $config->getReadableChainForExtendName($extends);
        if ($chain) {
            $this->log(sprintf('The chain of extends is %s', $chain));
        }
    }
}
