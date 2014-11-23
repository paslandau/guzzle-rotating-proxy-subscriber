<?php

namespace paslandau\GuzzleRotatingProxySubscriber\Time;


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