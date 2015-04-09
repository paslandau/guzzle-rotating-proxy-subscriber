<?php

namespace paslandau\GuzzleRotatingProxySubscriber\Interval;


use paslandau\GuzzleRotatingProxySubscriber\Random\RandomizerInterface;
use paslandau\GuzzleRotatingProxySubscriber\Random\SystemRandomizer;

class RandomTimeInterval extends BaseRandomInterval implements TimeIntervalInterface
{
    /**
     * @var int|null
     */
    private $lastActionTime;
    /**
     * @var TimeProviderInterface
     */
    private $timeProvider;

    /**
     * Time interval in seconds that is randomly choosen
     * @param int $from
     * @param int $to
     * @param RandomizerInterface $randomizer [optional]. Default: SystemRandomizer.
     * @param TimeProviderInterface $timeProvider [optional]. Default: SystemTimeProvider.
     */
    function __construct($from, $to, RandomizerInterface $randomizer = null, TimeProviderInterface $timeProvider = null)
    {
        parent::__construct($from, $to, $randomizer);
        if ($timeProvider === null) {
            $timeProvider = new SystemTimeProvider();
        }
        $this->timeProvider = $timeProvider;
    }

    /**
     * Checks if sufficient time has passed to satisfy the current time interval.
     * @return bool
     */
    public function isReady()
    {
        if ($this->lastActionTime === null) {
            return true;
        }
        $t = $this->getWaitingTime();
        return ($t <= 0);
    }

    /**
     * Gets the time in seconds the need to pass until $this->isReady becomes true.
     * @return int
     */
    public function getWaitingTime()
    {
        if ($this->lastActionTime === null) {
            return 0;
        }
        $diff = $this->lastActionTime - ($this->timeProvider->getTime() - $this->getCurrentInterval());
        return $diff;
    }

    /**
     * Resets the current time interval and set the time of the last action to now
     */
    public function restart()
    {
        $this->lastActionTime = $this->timeProvider->getTime();
        $this->currentInterval = null;
    }

    /**
     * Resets the current time interval and set the time of the last action to null.
     * This means that isReady will return true and getWaitingTime will return 0 until
     * $this->restart is called the next time.
     */
    public function reset()
    {
        $this->lastActionTime = null;
        $this->currentInterval = null;
    }

}