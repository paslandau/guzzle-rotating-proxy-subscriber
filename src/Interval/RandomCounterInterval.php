<?php

namespace paslandau\GuzzleRotatingProxySubscriber\Interval;


use paslandau\GuzzleRotatingProxySubscriber\Random\RandomizerInterface;

class RandomCounterInterval extends BaseRandomInterval implements RandomCounterIntervalInterface
{

    /**
     * @var int
     */
    private $counter;

    /**
     * Time interval in seconds that is randomly choosen
     * @param int $from
     * @param int $to
     * @param RandomizerInterface $randomizer [optional]. Default: SystemRandomizer.
     */
    function __construct($from, $to, RandomizerInterface $randomizer = null)
    {
        parent::__construct($from, $to, $randomizer);
        $this->counter = 0;
    }

    /**
     * @return int
     */
    public function getCounter()
    {
        return $this->counter;
    }

    /**
     * Returns true if the current random interval is lower or equal the counter value
     * @return bool
     */
    public function isReady(){
        $cur = $this->getCurrentInterval();
        if($this->counter >= $cur){
            return true;
        }
        return false;
    }

    /**
     * Increments the counter by 1 and returns the current counter value (after incrementing)
     * @return int
     */
    public function incrementCounter(){
        $this->counter++;
        return $this->counter;
    }

    /**
     * Sets the counter to 0 and the interval to null (so a new intervall will be choosen upon next call to $this->isReady())
     */
    public function restart(){
        $this->counter = 0;
        $this->currentInterval = null;
    }
}