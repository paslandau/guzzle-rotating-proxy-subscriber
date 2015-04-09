<?php namespace paslandau\GuzzleRotatingProxySubscriber\Random;

interface RandomizerInterface
{
    /**
     * Returns a random number between $from (inklusive) and $to (inklusive)
     * @param $from
     * @param $to
     * @return int
     */
    public function randNum($from, $to);

    /**
     * Returns a random key from the give $arr or false if the array is empty.
     * @param array $arr
     * @return mixed
     */
    public function randKey(array &$arr);
}