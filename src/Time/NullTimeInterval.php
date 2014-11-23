<?php

namespace paslandau\GuzzleRotatingProxySubscriber\Time;


class NullTimeInterval implements TimeIntervalInterface{
    /**
     * Returns always true.
     * @return bool
     */
    public function isReady()
    {
        return true;
    }

    /**
     * Returns always null
     * @return int
     */
    public function getWaitingTime()
    {
        return 0;
    }

    /**
     * Has no effect.
     */
    public function restart()
    {
        // does nothing
    }

    /**
     * Has no effect.
     */
    public function reset()
    {
        // does nothing
    }

} 