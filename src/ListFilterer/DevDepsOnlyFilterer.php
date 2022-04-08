<?php

namespace eiriksm\CosyComposer\ListFilterer;

class DevDepsOnlyFilterer implements FilterInterface
{
    use RequiresForPackageTrait;

    /**
     * @var \stdClass
     */
    protected $lockData;

    /**
     * @var \stdClass
     */
    protected $composerJson;

    public function __construct($composer_lock, $composer_json)
    {
        $this->lockData = $composer_lock;
        $this->composerJson = $composer_json;
    }

    public static function create($composer_lock, $composer_json)
    {
        return new self($composer_lock, $composer_json);
    }

    /**
     * {@inheritdoc}
     */
    public function filter(array $list): array
    {
        foreach ($list as $delta => $item) {
            $type = $this->getRequireTypeInComposerJsonForPackage($item->name);
            if (!$type) {
                continue;
            }
            if ($type === FilterInterface::REQUIRE_TYPE_REQUIRE_DEV) {
                unset($list[$delta]);
            }
        }
        return array_values($list);
    }
}
