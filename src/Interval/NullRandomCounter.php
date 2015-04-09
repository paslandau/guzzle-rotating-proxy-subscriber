<?php

namespace paslandau\GuzzleRotatingProxySubscriber\Interval;


class NullRandomCounter implements RandomCounterIntervalInterface{

    /**
     * Returns true
     * @return bool
     */
    public function isReady()
    {
        return true;
    }

    /**
     * Has no effect.
     */
    public function incrementCounter()
    {
        // does nothing
    }

    /**
     * Has no effect.
     */
    public function restart()
    {
        // does nothing
    }

    /**
     * @return int
     */
    public function getCounter()
    {
        return 0;
    }
}