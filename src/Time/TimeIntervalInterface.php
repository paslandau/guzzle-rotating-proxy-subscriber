<?php namespace paslandau\GuzzleRotatingProxySubscriber\Time;

interface TimeIntervalInterface
{
    /**
     * Checks if sufficient time has passed to satisfy the current time interval.
     * @return bool
     */
    public function isReady();

    /**
     * Gets the time in seconds the need to pass until $this->isReady becomes true.
     * @return int
     */
    public function getWaitingTime();

    /**
     * Restarts the current time interval and set the time of the last action to now
     */
    public function restart();

    /**
     * Resets the current time interval and set the time of the last action to null.
     * This means that isReady will return true and getWaitingTime will return 0 until
     * $this->restart is called the next time.
     */
    public function reset();
}