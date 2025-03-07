<?php

namespace eiriksm\CosyComposer;

use eiriksm\CosyComposer\Exceptions\ComposerInstallException;
use eiriksm\CosyComposer\Providers\Bitbucket;
use Violinist\Config\Config;

trait ComposerInstallTrait
{
    use CommandOutputTrait;

    /**
     * Does a composer install.
     *
     * @throws \eiriksm\CosyComposer\Exceptions\ComposerInstallException
     */
    protected function doComposerInstall(Config $config) : void
    {
        $this->log('Running composer install');
        $install_command = ['composer', 'install', '--no-ansi', '-n'];
        if (!$config->shouldRunScripts()) {
            $install_command[] = '--no-scripts';
        }
        try {
            if ($code = $this->execCommand($install_command, false, 1200)) {
                // Other status code than 0.
                $this->log($this->getLastStdOut(), Message::COMMAND);
                $this->log($this->getLastStdErr());
                throw new ComposerInstallException('Composer install failed with exit code ' . $code);
            }
        } catch (ComposerInstallException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->log($this->getLastStdOut(), Message::COMMAND);
            $this->log($this->getLastStdErr());
            throw new ComposerInstallException($e->getMessage());
        }

        $command_output = $this->getLastStdErr();
        if (!empty($command_output)) {
            $this->log($command_output, Message::COMMAND);
        }
        $this->log('composer install completed successfully');
    }

    protected function runAuthExport($hostname)
    {
        // If we have multiple auth tokens, export them all.
        if (!empty($this->tokens)) {
            foreach ($this->tokens as $token_hostname => $token) {
                $this->runAuthExportToken($token_hostname, $token);
            }
        }
        $this->runAuthExportToken($hostname, $this->userToken);
    }

    protected function runAuthExportToken($hostname, $token)
    {
        if (empty($token)) {
            return;
        }
        switch ($hostname) {
            case 'github.com':
                $this->execCommand(
                    ['composer', 'config', '--auth', 'github-oauth.github.com', $token],
                    false
                );
                break;

            case 'gitlab.com':
                $this->execCommand(
                    ['composer', 'config', '--auth', 'gitlab-oauth.gitlab.com', $token],
                    false
                );
                break;

            case 'bitbucket.org':
                if (Bitbucket::tokenIndicatesUserAppPassword($this->untouchedUserToken)) {
                    [$bitbucket_user, $app_password] = explode(':', $this->untouchedUserToken);
                    $this->execCommand(
                        ['composer', 'config', '--auth', 'http-basic.bitbucket.org', $bitbucket_user, $app_password],
                        false
                    );
                } else {
                    $this->execCommand(
                        ['composer', 'config', '--auth', 'http-basic.bitbucket.org', 'x-token-auth', $token],
                        false
                    );
                }
                break;

            default:
                $this->execCommand(
                    ['composer', 'config', '--auth', sprintf('gitlab-oauth.%s', $token), $hostname],
                    false
                );
                break;
        }
    }
}
