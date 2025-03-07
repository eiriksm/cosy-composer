<?php

namespace eiriksm\CosyComposer;

trait TokenAwareTrait
{
    /**
     * @var string
     */
    protected $untouchedUserToken;

    /**
     * @var string
     */
    protected $userToken;

    /**
     * @var array
     */
    protected $tokens = [];

    protected function setUntouchedUserToken(string $token)
    {
        if (empty($this->untouchedUserToken)) {
            $this->untouchedUserToken = $token;
        }
    }

    public function setAuthentication(string $user_token)
    {
        $this->setUntouchedUserToken($user_token);
        $this->userToken = $user_token;
    }

    public function getUntouchedUserToken()
    {
        return $this->untouchedUserToken;
    }
}
