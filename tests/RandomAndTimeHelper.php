<?php

use paslandau\GuzzleRotatingProxySubscriber\Random\RandomizerInterface;
use paslandau\GuzzleRotatingProxySubscriber\Time\TimeProviderInterface;

class RandomAndTimeHelper {
    /**
     * @var PHPUnit_Framework_MockObject_MockObject
     */
    private $randomMock;
    /**
     * @var PHPUnit_Framework_MockObject_MockObject
     */
    private $timeMock;
    /**
     * @var callable
     */
    private $getRandomNumberFn;
    /**
     * @var callable
     */
    private $getRandomTimeFn;

    /**
     * @var callable
     */
    private $getRandomKeyFn;

    /**
     * @var callable
     */
    private $getLastTimeFn;
    /**
     * @var callable
     */
    private $getLastNumberFn;
    /**
     * @var callable
     */
    private $getLastKeyFn;

    public function __construct(array $numbers = null, array $times = null, array $randKeys = null, PHPUnit_Framework_MockObject_MockObject $randomMock = null, PHPUnit_Framework_MockObject_MockObject $timeMock = null){
        $this->randomMock = $randomMock;
        $this->timeMock = $timeMock;
        $takenNumbers = [];
        $this->getLastNumberFn = function () use (&$takenNumbers) {
            return end($takenNumbers);
        };
        $this->getRandomNumberFn = function ($from = null, $to = null) use (&$numbers, &$takenNumbers) {
            $el = array_shift($numbers);
            $takenNumbers[] = $el;
            return $el;
        };

        $this->getLastKeyFn = function () use (&$takenKeys) {
            return end($takenKeys);
        };
        $this->getRandomKeyFn = function () use (&$randKeys, &$takenKeys) {
            $el = array_shift($randKeys);
            $takenKeys[] = $el;
            return $el;
        };

        $takenTimes = [];
        $this->getLastTimeFn = function () use (&$takenTimes) {
            return end($takenTimes);
        };
        $this->getRandomTimeFn = function () use (&$times, &$takenTimes) {
            $el = array_shift($times);
            $takenTimes[] = $el;
            return $el;
        };
    }

    /**
     * @return callable
     */
    public function getGetLastNumberFn()
    {
        return $this->getLastNumberFn;
    }

    /**
     * @return callable
     */
    public function getGetLastTimeFn()
    {
        return $this->getLastTimeFn;
    }

    /**
     * @return callable
     */
    public function getGetRandomNumberFn()
    {
        return $this->getRandomNumberFn;
    }

    /**
     * @return callable
     */
    public function getGetRandomTimeFn()
    {
        return $this->getRandomTimeFn;
    }

    /**
     * @return RandomizerInterface
     */
    public function getRandomMock()
    {
        return $this->randomMock;
    }

    /**
     * @return \paslandau\GuzzleRotatingProxySubscriber\Time\TimeProviderInterface
     */
    public function getTimeMock()
    {
        return $this->timeMock;
    }

    /**
     * @return callable
     */
    public function getGetLastKeyFn()
    {
        return $this->getLastKeyFn;
    }

    /**
     * @return callable
     */
    public function getGetRandomKeyFn()
    {
        return $this->getRandomKeyFn;
    }
}