<?php

namespace paslandau\GuzzleRotatingProxySubscriber\Interval;


class SystemTimeProvider implements TimeProviderInterface
{

    /**
     * Returns the current time in seconds. Uses time()
     * @return int
     */
    public function getTime(){
        return time();
    }
} 