<?php

namespace eiriksm\CosyComposer;

use Violinist\Config\Config;

class GroupUpdateItem implements UpdateItemInterface
{
    /**
     * @var \stdClass
     */
    private $rule;

    /**
     * @var array \stdClass
     */
    private $data = [];

    /**
     * @var Config
     */
    private $config;

    public function __construct(\stdClass $rule, \stdClass $data, Config $config)
    {
        $this->rule = $rule;
        $this->data[] = $data;
        $this->config = $config;
    }

    public function addData(\stdClass $data)
    {
        $this->data[] = $data;
    }

    public function getRule() : \stdclass
    {
        return $this->rule;
    }

    public function getData() : array
    {
        return $this->data;
    }

    public function getPackageName()
    {
        return $this->rule->name;
    }

    public function groupRuleMatches(string $package_name)
    {
        return $this->config->getMatcherFactory()->hasMatches($this->rule, $package_name);
    }
}
