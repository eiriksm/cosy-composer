<?php

namespace eiriksm\CosyComposerTest\integration;

class BlockListFromExtendTest extends BlockListTest
{

    protected function massageComposerJson(&$composer_contents)
    {
        $new_contents = (object) [];
        parent::massageComposerJson($composer_contents);
        // Parse into JSON.
        $composer_json_contents = json_decode($composer_contents);
        // Remove the block list, no matter which key we have used.
        foreach (['blocklist', 'blacklist'] as $key) {
            if (isset($composer_json_contents->extra->violinist->{$key})) {
                $new_contents->{$key} = $composer_json_contents->extra->violinist->{$key};
                unset($composer_json_contents->extra->violinist->{$key});
            }
        }
        // Now place the new contents inside another file.
        $filename = 'extra.json';
        $composer_json_contents->extra->violinist->extends = $filename;
        $new_contents = json_encode($new_contents);
        file_put_contents(sprintf('%s/%s', $this->dir, $filename), $new_contents);
        $composer_contents = json_encode($composer_json_contents);
    }
}
