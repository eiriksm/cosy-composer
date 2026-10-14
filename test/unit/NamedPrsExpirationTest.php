<?php

namespace eiriksm\CosyComposerTest\unit;

use eiriksm\CosyComposer\Providers\NamedPrs;
use PHPUnit\Framework\TestCase;

class NamedPrsExpirationTest extends TestCase
{
    public function testClosedRequestsAreKeptOutOfActiveRequests() : void
    {
        $named_prs = new NamedPrs();
        $request = [
            'number' => 123,
            'created_at' => '2026-01-01T00:00:00+00:00',
            'head' => [
                'ref' => 'violinist-update-psr-log',
            ],
        ];
        $named_prs->addFromPrData($request);
        $named_prs->addFromCommit($this->getSinglePackageCommit(), $request);

        self::assertCount(1, $named_prs->getAllPrsNamed());
        $named_prs->markPrClosed(123, '2026-02-26T00:00:00+00:00');

        self::assertCount(0, $named_prs->getAllPrsNamed());
        self::assertCount(1, $named_prs->getClosedPrsFromPackage('psr/log'));
        self::assertSame('2026-02-26T00:00:00+00:00', $named_prs->getClosedPrsFromPackage('psr/log')[0]['closed_at']);
    }

    public function testClosedHistoryCanBeLoadedWithoutAddingActiveRequest() : void
    {
        $named_prs = new NamedPrs();
        $request = [
            'number' => 123,
            'created_at' => '2026-01-01T00:00:00+00:00',
            'closed_at' => '2026-02-26T00:00:00+00:00',
            'head' => [
                'ref' => 'violinist-update-psr-log',
            ],
        ];
        $named_prs->addFromCommit($this->getSinglePackageCommit(), $request, false);

        self::assertCount(0, $named_prs->getAllPrsNamed());
        self::assertCount(1, $named_prs->getClosedPrsFromPackage('psr/log'));
    }

    public function testGroupMetadataMapsRequestToEveryPackage() : void
    {
        $named_prs = new NamedPrs();
        $request = [
            'number' => 456,
            'head' => [
                'ref' => 'violinist-update-group-symfony',
            ],
        ];
        $commit_message = 'Update group\n\n------\nupdate_data:\n  - package: symfony/console\n    from: 6.0.0\n    to: 7.0.0\n  - package: symfony/http-kernel\n    from: 6.0.0\n    to: 7.0.0';
        $named_prs->addFromCommit($commit_message, $request);

        self::assertCount(1, $named_prs->getPrsFromPackage('symfony/console'));
        self::assertCount(1, $named_prs->getPrsFromPackage('symfony/http-kernel'));
        self::assertCount(1, $named_prs->getOpenPrsWithPackages());
        $packages = $named_prs->getOpenPrsWithPackages()[0]['packages'];
        self::assertCount(2, $packages);
        self::assertContains('symfony/console', $packages);
        self::assertContains('symfony/http-kernel', $packages);
    }

    private function getSinglePackageCommit() : string
    {
        return 'Update psr/log\n\n------\nupdate_data:\n    package: psr/log\n    from: 1.0.0\n    to: 1.1.4';
    }
}
