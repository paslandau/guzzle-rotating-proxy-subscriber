<?php namespace paslandau\GuzzleRotatingProxySubscriber\Time;

interface TimeProviderInterface
{
    /**
     * Returns the current time in seconds.
     * @return int
     */
    public function getTime();
}