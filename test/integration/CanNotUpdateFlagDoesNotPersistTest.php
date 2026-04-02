<?php

namespace eiriksm\CosyComposerTest\integration;

use eiriksm\CosyComposer\Message;
use Violinist\ComposerUpdater\Exception\NotUpdatedException;

/**
 * Test that $should_indicate_can_not_update_if_unupdated does not persist
 * between iterations of the update loop.
 *
 * @see https://github.com/eiriksm/cosy-composer/issues/322
 */
class CanNotUpdateFlagDoesNotPersistTest extends ComposerUpdateIntegrationBase
{
    protected $composerAssetFiles = 'composer-not-update-flag-persist';

    /**
     * The update JSON with two packages:
     * - psr/log: constrained to ^1.0, latest is 3.0.0 (beyond constraint)
     * - psr/cache: constrained to ^1.0, latest is 1.0.2 (within constraint)
     */
    protected $updateJson = '{
    "installed": [
        {
            "name": "psr/log",
            "version": "1.0.2",
            "latest": "3.0.0",
            "latest-status": "update-possible",
            "direct-dependency": true
        },
        {
            "name": "psr/cache",
            "version": "1.0.1",
            "latest": "1.0.2",
            "latest-status": "semver-safe-update",
            "direct-dependency": true
        }
    ]
}';

    public function testCanNotUpdateFlagDoesNotPersist()
    {
        $this->runtestExpectedOutput();
        $output = $this->cosy->getOutput();
        $unupdateable_messages = [];
        $not_updated_messages = [];
        foreach ($output as $message) {
            if ($message->getType() === Message::UNUPDATEABLE) {
                $unupdateable_messages[] = $message->getMessage();
            }
            if ($message->getType() === Message::NOT_UPDATED) {
                $not_updated_messages[] = $message->getMessage();
            }
        }

        // psr/log should have an UNUPDATEABLE message because its latest
        // version (3.0.0) is beyond the ^1.0 constraint.
        $found_log_unupdateable = false;
        foreach ($unupdateable_messages as $msg) {
            if (strpos($msg, 'psr/log') !== false) {
                $found_log_unupdateable = true;
            }
        }
        self::assertTrue($found_log_unupdateable, 'psr/log should have an UNUPDATEABLE message');

        // psr/cache should NOT have an UNUPDATEABLE message - its latest
        // version (1.0.2) is within the ^1.0 constraint. If the flag persisted
        // from psr/log, it would incorrectly get an UNUPDATEABLE message.
        $found_cache_unupdateable = false;
        foreach ($unupdateable_messages as $msg) {
            if (strpos($msg, 'psr/cache') !== false) {
                $found_cache_unupdateable = true;
            }
        }
        self::assertFalse($found_cache_unupdateable, 'psr/cache should NOT have an UNUPDATEABLE message - the flag must not persist from psr/log');
    }

    public function handleExecutorReturnCallback(array $cmd, &$return)
    {
        // Both update commands should throw NotUpdatedException to test
        // how the flag affects the catch block behavior.
        if (in_array('update', $cmd) && (in_array('psr/log', $cmd) || in_array('psr/cache', $cmd))) {
            throw new NotUpdatedException();
        }
    }
}
