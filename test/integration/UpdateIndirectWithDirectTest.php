<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test to make sure indirect can be updated with in several ways.
 *
 * In this test we have a project which has 2 updates available. One is
 * doctrine/annotations and the other is drupal/captcha.
 *
 * We have only drupal and the module drupal/recaptca as our dependencies.
 *
 * The annnotations
 * package is a dependency of drupal/core, but since drupal/core is a dependency
 * of drupal/recaptcha as well, the annotations package is kind of a dependency
 * of drupal/recaptcha too. And our logic was this. If we are going to try to
 * update drupal/recaptcha at least once, then probably all other dependencies
 * are going to be updated. But the problem is this. If we just try to update it
 * because of the annotations package, then this update will fail when we check
 * if it was in fact updated. And then neither the main package
 * (drupal/recaptcha - since it does not have an update) or drupal/captcha will
 * end up updated, since the validation logic bails out when we tried to update
 * annotations. Even if in theory we could have succeeded in updating another
 * dependency, for example drupal/captcha. Wheh, what a refactor that was.
 */
class UpdateIndirectWithDirectTest extends ComposerUpdateIntegrationBase
{
    protected $composerAssetFiles = 'composer.indirect.direct.multiple_options';
    protected $hasUpdatedPsrLog = false;
    protected $hasUpdatedPsrCache = false;
    protected $packageForUpdateOutput = 'drupal/recaptcha';
    protected $usesDirect = false;
    protected $checkPrUrl = true;

    public function testUpdateCorrectOne()
    {
        $this->runtestExpectedOutput();
        // We should have tried and succeeded to update the dependencies of
        // drupal/recaptcha here.
        self::assertEquals('Update dependencies of drupal/recaptcha', $this->prParams["title"]);
    }

    protected function createUpdateJsonFromData($package, $version, $new_version)
    {
        return '{"installed": [{"name": "doctrine/annotations", "version": "1.13.3", "latest": "2.0.5", "latest-status": "semver-safe-update"}, {"name": "drupal/captcha", "version": "2.0.4", "latest": "2.0.5", "latest-status": "semver-safe-update"}]}';
    }
}
