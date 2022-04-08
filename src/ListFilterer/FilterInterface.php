<?php

namespace eiriksm\CosyComposer\ListFilterer;

interface FilterInterface
{
    const REQUIRE_TYPE_REQUIRE = 'require';
    const REQUIRE_TYPE_REQUIRE_DEV = 'require-dev';

    const REQUIRE_TYPES = [
        self::REQUIRE_TYPE_REQUIRE,
        self::REQUIRE_TYPE_REQUIRE_DEV
    ];

    /**
     * Filter an array of updates into a filtered list of updates.
     *
     * @param array $list
     *   The updates in question.
     * @return array
     *   The new, filtered list.
     */
    public function filter(array $list) : array;
}
