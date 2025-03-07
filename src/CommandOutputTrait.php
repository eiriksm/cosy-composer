<?php

namespace eiriksm\CosyComposer;

trait CommandOutputTrait
{
    /**
     * @return string
     */
    public function getLastStdErr()
    {
        $output = $this->executer->getLastOutput();
        return !empty($output['stderr']) ? $output['stderr'] : '';
    }

    /**
     * @return string
     */
    public function getLastStdOut()
    {
        $output = $this->executer->getLastOutput();
        return !empty($output['stdout']) ? $output['stdout'] : '';
    }
}
