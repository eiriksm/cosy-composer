<?php

namespace eiriksm\CosyComposer;

use eiriksm\CosyComposer\SecurityChecker\NativeComposerChecker;
use eiriksm\CosyComposer\SecurityChecker\SecurityCheckerInterface;

class SecurityCheckerFactory
{
    /**
     * @var SecurityCheckerInterface
     */
    private $checker;

    public function setChecker(SecurityCheckerInterface $checker)
    {
        $this->checker = $checker;
    }

    public function getChecker()
    {
        if (!$this->checker instanceof SecurityCheckerInterface) {
            $this->checker = new NativeComposerChecker();
        }
        return $this->checker;
    }
}
