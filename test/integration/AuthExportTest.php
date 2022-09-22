<?php

namespace eiriksm\CosyComposerTest\integration;

/**
 * Test for a default commit message.
 */
class AuthExportTest extends ComposerUpdateIntegrationBase
{
    const FAKE_GITHUB_TOKEN = 'derpy123';
    const FAKE_GITLAB_SELF_HOSTED_TOKEN = 'gitlab-yeah-token';
    const FAKE_BITBUCKET_TOKEN = 'totally-bitty-bucket';
    const FAKE_GITLAB_TOKEN = 'this-is-gitlab-right-here';

    private $hasExportedGithub = false;
    private $hasExportedGitlab = false;
    private $hasExportedBitbucket = false;
    private $hasExportedGitlabSelfHosted = false;

    // Let's just reuse another asset file.
    protected $composerAssetFiles = 'composer.allow';
    protected $packageForUpdateOutput = 'psr/cache';

    public function testTokensAuthExported()
    {
        $this->cosy->setTokens([
            'github.com' => self::FAKE_GITHUB_TOKEN,
            'some.gitlab.company.com' => self::FAKE_GITLAB_SELF_HOSTED_TOKEN,
            'bitbucket.org' => self::FAKE_BITBUCKET_TOKEN,
            'gitlab.com' => self::FAKE_GITLAB_TOKEN,
            'another_one_but_empty' => '',
        ]);
        $this->runtestExpectedOutput();
        self::assertTrue($this->hasExportedGithub);
        self::assertTrue($this->hasExportedGitlab);
        self::assertTrue($this->hasExportedBitbucket);
        self::assertTrue($this->hasExportedGitlabSelfHosted);
    }

    protected function handleExecutorReturnCallback($cmd, &$return)
    {
        $cmd_string = implode(' ', $cmd);
        if ($cmd_string === sprintf('composer config --auth github-oauth.github.com %s', self::FAKE_GITHUB_TOKEN)) {
            $this->hasExportedGithub = true;
        }
        if ($cmd_string === sprintf('composer config --auth gitlab-oauth.%s some.gitlab.company.com', self::FAKE_GITLAB_SELF_HOSTED_TOKEN)) {
            $this->hasExportedGitlabSelfHosted = true;
        }
        if ($cmd_string === sprintf('composer config --auth http-basic.bitbucket.org x-token-auth %s', self::FAKE_BITBUCKET_TOKEN)) {
            $this->hasExportedBitbucket = true;
        }
        if ($cmd_string === sprintf('composer config --auth gitlab-oauth.gitlab.com %s', self::FAKE_GITLAB_TOKEN)) {
            $this->hasExportedGitlab = true;
        }
    }
}
