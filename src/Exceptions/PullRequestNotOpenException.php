<?php

namespace eiriksm\CosyComposer\Exceptions;

/**
 * Thrown when we try to close a pull request that is no longer open.
 *
 * For example because it was merged or closed after we listed it.
 */
class PullRequestNotOpenException extends \Exception
{
}
