<?php
use paslandau\GuzzleRotatingProxySubscriber\Random\RandomizerInterface;
use paslandau\GuzzleRotatingProxySubscriber\Time\RandomTimeInterval;
use paslandau\GuzzleRotatingProxySubscriber\Time\TimeProviderInterface;

include_once __DIR__ . "/../RandomAndTimeHelper.php";

class RandomTimeIntervalTest extends PHPUnit_Framework_TestCase
{
    private function getHelper(array $numbers, array $times){
        $randomMock = $this->getMock(RandomizerInterface::class);
        $timeMock = $this->getMock(TimeProviderInterface::class);
        $h = new RandomAndTimeHelper($numbers, $times, null, $randomMock,$timeMock);
        $randomMock->expects($this->any())->method("randNum")->will($this->returnCallback($h->getGetRandomNumberFn()));
        $timeMock->expects($this->any())->method("getTime")->will($this->returnCallback($h->getGetRandomTimeFn()));

        return $h;
    }

    public function test_ShouldGenerateRandomCurrentInterval()
    {
        $numbers = [4, 6, 3, 7, 1, 9, 12];
        $times = [5, 10, 15, 20, 25, 30];

        $h = $this->getHelper($numbers,$times);
        $getLastTime = $h->getGetLastTimeFn();
        $getLastNumber = $h->getGetLastNumberFn();

        $interval = new RandomTimeInterval(0, 15, $h->getRandomMock(), $h->getTimeMock());

        $time = $interval->getWaitingTime();
        $this->assertEquals(0, $time, "First call, should be 0 since lastActionTime is null");

        $interval->restart(); // takes first number '5' from $times
        $firstTime = $getLastTime();

        $time = $interval->getWaitingTime(); // takes second number '10' from $times and first number '4' from randomNumbers
        $rand = $getLastNumber();
        $secondTime = $getLastTime();
        $expected = $firstTime - ($secondTime - $rand);
        $this->assertEquals($expected, $time, "Should be $expected using start $firstTime, current $secondTime and random $rand");

        $time = $interval->getWaitingTime(); // takes third number '15' from $times and no number from randomNumbers
        $thirdTime = $getLastTime();
        $expected = $firstTime - ($thirdTime - $rand);
        $this->assertEquals($expected, $time, "Should be $expected using start $firstTime, current $thirdTime and random $rand");

        $interval->restart(); // takes fourth number '20' from $times
        $fourthTime = $getLastTime();

        $time = $interval->getWaitingTime(); // takes fifth number '25' from $times and second number '6' from randomNumbers
        $secondRand = $getLastNumber();
        $fifthTime = $getLastTime();
        $expected = $fourthTime - ($fifthTime - $secondRand);
        $this->assertEquals($expected, $time, "Should be $expected using start $fourthTime, current $fifthTime and random $secondRand");

        $interval->reset();
        $time = $interval->getWaitingTime();
        $this->assertEquals(0, $time, "First call after reset, should be 0 since lastActionTime is null");

    }
}
 