<?php namespace paslandau\GuzzleRotatingProxySubscriber\Interval;

interface RandomCounterIntervalInterface
{
    /**
     * Returns true if the current random interval is lower or equal the counter value
     * @return bool
     */
    public function isReady();

    /**
     * Increments the counter by 1 and returns the current counter value (after incrementing)
     * @return int
     */
    public function incrementCounter();

    /**
     * Sets the counter to 0 and the interval to null (so a new intervall will be choosen upon next call to $this->isReady())
     */
    public function restart();

    /**
     * @return int
     */
    public function getCounter();
}