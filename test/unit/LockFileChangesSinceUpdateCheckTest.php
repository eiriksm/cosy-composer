<?php

namespace eiriksm\CosyComposerTest\unit;

use eiriksm\CosyComposer\Updater\IndividualUpdater;
use PHPUnit\Framework\TestCase;

class LockFileChangesSinceUpdateCheckTest extends TestCase
{
    protected string $dir;

    public function setUp() : void
    {
        $this->dir = sys_get_temp_dir() . '/' . uniqid('lock-changes-');
        mkdir($this->dir);
    }

    public function tearDown() : void
    {
        @unlink($this->dir . '/composer.lock');
        @rmdir($this->dir);
    }

    /**
     * @dataProvider getLockFileChangesData
     *
     * @param string[] $packages
     * @param string[] $expected
     */
    public function testLockFileChanges(?string $lock_on_disk, ?\stdClass $lockdata, array $packages, array $expected) : void
    {
        if ($lock_on_disk !== null) {
            file_put_contents($this->dir . '/composer.lock', $lock_on_disk);
        }
        $updater = new class extends IndividualUpdater {
            /**
             * @param string[] $packages
             * @param mixed $lockdata
             *
             * @return string[]
             */
            public function getChanges(array $packages, $lockdata) : array
            {
                return $this->getLockFileChangesSinceUpdateCheck($packages, $lockdata);
            }
        };
        $updater->setComposerJsonDir($this->dir);
        self::assertEquals($expected, $updater->getChanges($packages, $lockdata));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function getLockFileChangesData() : array
    {
        $lock = self::createLock('1.0.0', 'abc');
        return [
            'no lock file on disk' => [null, $lock, ['psr/log'], []],
            'no lock data' => [json_encode($lock), null, ['psr/log'], []],
            'invalid lock file on disk' => ['not json', $lock, ['psr/log'], []],
            'same lock' => [json_encode($lock), $lock, ['psr/log'], []],
            'package not in lock' => [json_encode($lock), $lock, ['psr/container'], []],
            'version changed' => [
                json_encode(self::createLock('1.0.2', 'def')),
                $lock,
                ['psr/log'],
                ['psr/log (expected 1.0.0, found 1.0.2)'],
            ],
            'only reference changed' => [
                json_encode(self::createLock('1.0.0', 'def')),
                $lock,
                ['psr/log'],
                ['psr/log (expected 1.0.0 at abc, found 1.0.0 at def)'],
            ],
        ];
    }

    protected static function createLock(string $version, string $reference) : \stdClass
    {
        return (object) [
            'packages' => [
                (object) [
                    'name' => 'psr/log',
                    'version' => $version,
                    'source' => (object) [
                        'type' => 'git',
                        'reference' => $reference,
                    ],
                ],
            ],
            'packages-dev' => [],
        ];
    }
}
