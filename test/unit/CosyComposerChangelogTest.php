<?php

namespace eiriksm\CosyComposerTest\unit;

use eiriksm\CosyComposerTest\GetCosyTrait;
use eiriksm\CosyComposerTest\GetExecuterTrait;
use PHPUnit\Framework\TestCase;

class CosyComposerChangelogTest extends TestCase
{
    use GetExecuterTrait;
    use GetCosyTrait;

    public function testChangeLogPackageNotFound()
    {
        $c = $this->getMockCosy();
        // Of course this should not be possible, but what does one do for coverage, eh?
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Did not find the requested package (vendor/package) in the lockfile. This is probably an error');
        $c->retrieveChangeLog('vendor/package', (object) ['packages' => [], 'packages-dev' => []], 1, 2);
    }

    public function testChangeLogRepoUnknownSource()
    {
        $c = $this->getMockCosy();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unknown source or non-git source found for vendor/package. Aborting.');
        $c->retrieveChangeLog('vendor/package', json_decode(json_encode(['packages' => [
            [
                'name' => 'vendor/package',
            ],
        ]])), 1, 2);
    }

    public function testChangeLogRepoCloneError()
    {
        $c = $this->getMockCosy();
        $called = false;
        $mock_executer = $this->getMockExecuterWithReturnCallback(function ($command) use (&$called) {
            if (strpos($command, 'git clone http://example.com/vendor/package /tmp/') === 0) {
                $called = true;
            }
            return 0;
        });
        $c->setExecuter($mock_executer);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The changelog string was empty for package vendor/package');
        $c->retrieveChangeLog('vendor/package', json_decode(json_encode(['packages' => [
            [
                'name' => 'vendor/package',
                'source' => [
                    'type' => 'git',
                    'url' => 'http://example.com/vendor/package',
                ],
            ],
        ]])), 1, 2);
        $this->assertEquals(true, $called);
    }

    public function testChangeLogRegular()
    {
        $c = $this->getMockCosy();
        $called = false;
        $mock_executer = $this->getMockExecuterWithReturnCallback(function ($command) use (&$called) {
            if (strpos($command, 'log 1..2 --oneline') > 0) {
                $called = true;
            }
            return 0;
        });
        $mock_executer->expects($this->once())
            ->method('getLastOutput')
            ->willReturn([
                'stdout' => "112233 This is the first line\n445566 This is the second line"
                ]);
        $c->setExecuter($mock_executer);
        $log = $c->retrieveChangeLog('vendor/package', json_decode(json_encode(['packages' => [
            [
                'name' => 'vendor/package',
                'source' => [
                    'type' => 'git',
                    'url' => 'https://github.com/vendor/package',
                ],
            ],
        ]])), 1, 2);
        $this->assertEquals('- [112233](https://github.com/vendor/package/commit/112233) This is the first line
- [445566](https://github.com/vendor/package/commit/445566) This is the second line
', $log->getAsMarkdown());
        $this->assertEquals(true, $called);
    }

    public function testChangeLogSuperLong()
    {
        $c = $this->getMockCosy();
        $called = false;
        $mock_executer = $this->getMockExecuterWithReturnCallback(function ($command) use (&$called) {
            if (strpos($command, 'log 1..2 --oneline') > 0) {
                $called = true;
            }
            return 0;
        });
        // Use the one-line output of a comparison between Drupal 8.4 and 8.5.
        $one_line_example_output = file_get_contents(__DIR__ . '/../fixtures/git-log-one-line-super-long.txt');
        $mock_executer->expects($this->once())
            ->method('getLastOutput')
            ->willReturn([
                'stdout' => $one_line_example_output,
            ]);
        $c->setExecuter($mock_executer);
        $log = $c->retrieveChangeLog('drupal/core', json_decode(json_encode(['packages' => [
            [
                'name' => 'drupal/core',
                'source' => [
                    'type' => 'git',
                    'url' => 'https://github.com/drupal/core',
                ],
            ],
        ]])), 1, 2);
        $this->assertEquals(file_get_contents(__DIR__ . '/../fixtures/git-log-one-line-super-long-markdown.txt'), $log->getAsMarkdown());
        $this->assertEquals(true, $called);
    }
}
