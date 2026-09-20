<?php

namespace eiriksm\CosyComposerTest\integration;

use Violinist\SymfonyCloudSecurityChecker\SecurityChecker;

class DefaultBranchTest extends ComposerUpdateIntegrationBase
{
    protected ?string $packageForUpdateOutput = 'psr/log';
    protected ?string $packageVersionForFromUpdateOutput = '1.0.0';
    protected ?string $packageVersionForToUpdateOutput = '1.0.2';
    protected ?string $composerAssetFiles = 'composer.default_branch';

    /**
     * @dataProvider defaultBranchProvider
     */
    public function testUpdates($set_security, $expected_default_branch)
    {
        if ($set_security) {
            $checker = $this->createMock(SecurityChecker::class);
            $checker->method('checkDirectory')
                ->willReturn([
                    'psr/log' => true,
                ]);
            $this->cosy->getCheckerFactory()->setChecker($checker);
        }
        $this->runtestExpectedOutput();
        self::assertEquals($this->prParams['base'], $expected_default_branch);
    }

    public static function defaultBranchProvider()
    {
        return [
            [
                false,
                'main-which-we-use-for-the-tests',
            ],
            [
                true,
                'other-main-which-we-use-for-security-tests',
            ],
        ];
    }
}
