<?php

namespace eiriksm\CosyComposer;

trait TemporaryDirectoryAwareTrait
{
    /**
     * @var string
     */
    protected $tmpDir;

    public function setTmpDir(string $tmpDir)
    {
        $this->tmpDir = $tmpDir;
    }
}
