<?php

namespace eiriksm\CosyComposerTest\integration;

use Github\Exception\ValidationFailedException;
use Violinist\Slug\Slug;
use Violinist\SymfonyCloudSecurityChecker\SecurityChecker;

/**
 * Test for automerge being enabled for security, but no security updates.
 */
class AutomergeSecurityUpdateTest extends ComposerUpdateIntegrationBase
{
    protected $composerAssetFiles = 'composer.automerge_sec_update';
    protected $hasUpdatedPsrLog = false;
    protected $hasUpdatedPsrCache = false;
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.1.3';
    protected $packageVersionForToUpdateOutput = '1.1.4';
    protected $hasAutoMerge = true;
    protected $checkPrUrl = true;
    private $isUpdate = false;

    public function setUp()
    {
        parent::setUp();
        $checker = $this->createMock(SecurityChecker::class);
        $checker->method('checkDirectory')
            ->willReturn([
                'psr/log' => true,
            ]);
        $this->cosy->getCheckerFactory()->setChecker($checker);
    }

    protected function getBranchesFlattened()
    {
        if (!$this->isUpdate) {
            return [];
        }
        return ['psrlog113114'];
    }

    protected function createPullRequest(Slug $slug, array $params)
    {
        if (!$this->isUpdate) {
            return parent::createPullRequest($slug, $params);
        }
        throw new ValidationFailedException('I want you to update please');
    }

    protected function getPrsNamed()
    {
        if (!$this->isUpdate) {
            return [];
        }
        return [
            'psrlog113114' => [
                'base' => [
                    'sha' => 456,
                ],
                'title' => 'not the same as the other',
                'number' => 666,
            ],
        ];
    }

    /**
     * @dataProvider getUpdateVariations
     */
    public function testAutomerge($should_have_updated)
    {
        $this->isUpdate = $should_have_updated;
        $this->checkPrUrl = !$should_have_updated;
        $this->runtestExpectedOutput();
    }

    public function getUpdateVariations()
    {
        return [
            [true],
            [false],
        ];
    }
}
