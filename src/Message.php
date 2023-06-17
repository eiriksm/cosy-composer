<?php

namespace eiriksm\CosyComposer;

class Message
{

    /**
     * @var string
     */
    protected $message;

    /**
     * @var int
     */
    protected $timestamp;

    /**
     * @var string
     */
    protected $type;

    /**
     * @return mixed
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * @param mixed $context
     */
    public function setContext($context)
    {
        $this->context = $context;
    }

    /**
     * @var mixed
     */
    protected $context;

    const MESSAGE = 'message';
    const UPDATE = 'update';
    const ERROR = 'error';
    const PR_URL = 'pr_url';
    const CONCURRENT_THROTTLED = 'concurrent_throttled';
    const BLACKLISTED = 'blacklisted';
    const PR_EXISTS = 'pr_exists';
    const UNUPDATEABLE = 'unupdate';
    const NOT_UPDATED = 'notupdated';
    const COMMAND = 'command';
    const VIOLINIST_ERROR = 'violinist_error';

    /**
     * Message constructor.
     */
    public function __construct($message, $type = self::MESSAGE)
    {
        $this->message = $message;
        $this->type = $type;
        $this->timestamp = time();
    }

  /**
   * @return string
   */
    public function getMessage()
    {
        return $this->message;
    }

    public function getTimestamp()
    {
        return $this->timestamp;
    }

    public function getType()
    {
        return $this->type;
    }
}
