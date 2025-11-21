<?php

namespace eiriksm\CosyComposerTest\unit;

use eiriksm\CosyComposer\Helpers;
use PHPUnit\Framework\TestCase;

class CosyComposerHelperUnitTest extends TestCase
{

    const TEMP_DIR_NAME = 'my-completely-unique-test-dir';

    private $filesToUnlink = [];
    private $dirsToUnlink = [];

    public function setUp(): void
    {
        parent::setUp();
        $dir = sprintf('%s/other/nested', self::getMyTempDir());
        $this->dirsToUnlink[] = $dir;
        // But also the parents of it.
        $this->dirsToUnlink[] = sprintf('%s/%s/other', sys_get_temp_dir(), self::TEMP_DIR_NAME);
        $this->dirsToUnlink[] = self::getMyTempDir();
        mkdir($dir, 0777, true);
        // And let's place a file there.
        $file = sprintf('%s/test.json', $dir);
        file_put_contents($file, json_encode([
            'require' => [
                'completely/different' => '1.0.0',
            ],
        ]));
        $this->filesToUnlink[] = $file;
        // Now one that is empty.
        $file = sprintf('%s/empty.json', $dir);
        file_put_contents($file, '');
        $this->filesToUnlink[] = $file;
        // Now one with invalid json.
        $file = sprintf('%s/invalid.json', $dir);
        file_put_contents($file, 'not: json');
        $this->filesToUnlink[] = $file;
        // Now one with an additional level of merge plugin.
        $file = sprintf('%s/with-merge.json', $dir);
        file_put_contents($file, json_encode([
            'require' => [],
            'require-dev' => [],
            'extra' => [
                'merge-plugin' => [
                    'include' => [
                        'other/nested/sibling.json',
                    ],
                ],
            ],
        ]));
        $this->filesToUnlink[] = $file;
        $file = sprintf('%s/sibling.json', $dir);
        file_put_contents($file, json_encode([
            'require' => [
                'nested/nested' => '1.0.0',
            ],
        ]));
        $this->filesToUnlink[] = $file;
    }

    public function tearDown(): void
    {
        parent::tearDown();
        // Delete the files.
        foreach ($this->filesToUnlink as $file) {
            unlink($file);
        }
        foreach ($this->dirsToUnlink as $dir) {
            rmdir($dir);
        }
    }

    /**
     * Test the helper, but this time with merge plugin data.
     *
     * @dataProvider getComposerJsonVariations
     */
    public function testComposerNameWithMergePlugin($json, $name, $expected)
    {
        $tmp_dir = self::getMyTempDir();
        self::assertEquals($expected, Helpers::getComposerJsonName($json, $name, $tmp_dir));
    }

    public static function getMyTempDir()
    {
        return sprintf('%s/%s', sys_get_temp_dir(), self::TEMP_DIR_NAME);
    }

    public static function getComposerJsonVariations()
    {
        $standard_json = (object) [
            'require' => (object) [
                'camelCase/other' => '1.0',
                'regular/case' => '1.0',
                'UPPER/CASE' => '1.0',
            ],
            'require-dev' => (object) [
                'camelCaseDev/other' => '1.0',
                'regulardev/case' => '1.0',
                'UPPERDEV/CASE' => '1.0',
            ],
            'extra' => (object)[
                'merge-plugin' => (object) [
                    'include' => [
                        'other/nested/non-existing.json',
                        'other/nested/empty.json',
                        'other/nested/invalid.json',
                        'other/nested/test.json',
                    ],
                    'require' => [
                        'other/nested/with-merge.json',
                    ],
                ],
            ],
        ];
        return [
            [$standard_json, 'completely/Different', 'completely/different'],
            [$standard_json, 'nested/nested', 'nested/nested'],
        ];
    }
}
