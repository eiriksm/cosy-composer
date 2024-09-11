<?php

namespace eiriksm\CosyComposer;

use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Filesystem;

class ComposerFileGetter
{

    /**
     * @var Filesystem
     */
    protected $fs;

    public function __construct(FilesystemAdapter $adapter)
    {
        $this->fs = new Filesystem($adapter);
    }

    public function hasComposerFile()
    {
        return $this->fs->fileExists('composer.json');
    }

    public function getComposerJsonData()
    {
        $data = $this->fs->read('composer.json');
        if (false == $data) {
            return false;
        }
        $json = @json_decode($data);
        if (false == $json) {
            return false;
        }
        return $json;
    }
}
