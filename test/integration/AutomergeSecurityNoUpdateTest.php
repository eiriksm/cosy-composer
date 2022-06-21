<?php

namespace eiriksm\CosyComposerTest\integration;

use Github\Exception\ValidationFailedException;
use Violinist\Slug\Slug;

/**
 * Test for automerge being enabled for security, but no security updates.
 */
class AutomergeSecurityNoUpdateTest extends ComposerUpdateIntegrationBase
{
    protected $composerAssetFiles = 'composer.automerge_sec_no_update';
    protected $hasUpdatedPsrLog = false;
    protected $hasUpdatedPsrCache = false;
    protected $packageForUpdateOutput = 'psr/log';
    protected $packageVersionForFromUpdateOutput = '1.1.3';
    protected $packageVersionForToUpdateOutput = '1.1.4';
    protected $hasAutoMerge = false;
    protected $checkPrUrl = true;

    protected function createPullRequest(Slug $slug, array $params)
    {
        if (!$this->isUpdate) {
            return parent::createPullRequest($slug, $params);
        }
        throw new ValidationFailedException('I want you to update please');
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
