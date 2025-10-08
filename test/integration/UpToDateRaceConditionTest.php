<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\Providers\NamedPrs;

class UpToDateRaceConditionTest extends ComposerUpdateIntegrationBase
{
    protected $composerAssetFiles = 'composer-psr-log';
    protected $packageVersionForFromUpdateOutput = '1.0.0';
    protected $packageVersionForToUpdateOutput = '1.0.2';
    protected $packageForUpdateOutput = 'psr/log';

    private $hasPushed = false;
    private $prsNamedCount = 0;

    public function testUpdatesRunButErrorPushing()
    {
        $this->runtestExpectedOutput();
        self::assertFalse($this->hasPushed, 'The update should not have been pushed as the branch was up to date.');
    }

    protected function handleExecutorReturnCallback($cmd, &$return)
    {
        if ($cmd == ['git', 'push', 'origin', 'psrlog100102', '--force']) {
            $this->hasPushed = true;
        }
    }

    protected function getBranchesFlattened()
    {
        return [
            'psrlog100102',
        ];
    }

    protected function getPrsNamed(): NamedPrs
    {
        // The first time this is called, we return one sha. The next time we
        // return a different one. The next one it should be the same as the
        // main branch sha.
        $sha = 456;
        $this->prsNamedCount++;
        if ($this->prsNamedCount == 2) {
            $sha = $this->getDefaultSha();
        }
        return NamedPrs::createFromArray([
            'psrlog100102' => [
                'base' => [
                    'sha' => $sha,
                ],
                'title' => 'Update psr/log from 1.0.0 to 1.0.2',
                'number' => 666,
                'head' => [
                    'ref' => 'psrlog100102',
                ],
            ],
        ]);
    }
}
