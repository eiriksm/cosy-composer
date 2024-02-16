<?php

namespace eiriksm\CosyComposer;

use Violinist\SymfonyCloudSecurityChecker\SecurityChecker;

class SecurityCheckerFactory
{
    /**
     * @var SecurityChecker
     */
    private $checker;

    public function setChecker(SecurityChecker $checker)
    {
        $this->checker = $checker;
    }

    public function getChecker()
    {
        if (!$this->checker instanceof SecurityChecker) {
            $this->checker = new NativeComposerChecker();
        }
        return $this->checker;
    }
}
