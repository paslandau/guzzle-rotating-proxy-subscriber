<?php

namespace paslandau\GuzzleRotatingProxySubscriber\Interval;


interface RandomIntervalInterface {



    /**
     * Checks if the interval is expired (return true if that's the case)
     * @return bool
     */
    public function isReady();

    /**
     * Restarts the interval
     * @return void
     */
    public function restart();
}