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
     *  Returns a random key from the give $arr.
     * @param array &$arr is passed by reference
     * @return mixed
     */
    public function randKey(array &$arr){
        return array_rand($arr);
    }
} 