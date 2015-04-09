<?php

namespace paslandau\GuzzleRotatingProxySubscriber\Random;


class SystemRandomizer implements RandomizerInterface
{
    /**
     * Returns a random number between $from (inklusive) and $to (inklusive)
     * @param int $from
     * @param int $to
     * @return int
     */
    public function randNum($from,$to){
        return rand($from,$to);
    }

    /**
     * {@inheritDoc}
     */
    public function randKey(array &$arr){
        if(count($arr) == 0){
            return false;
        }
        return array_rand($arr);
    }
} 