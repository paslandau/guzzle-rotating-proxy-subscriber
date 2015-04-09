<?php
use paslandau\GuzzleRotatingProxySubscriber\Interval\RandomCounterInterval;
use paslandau\GuzzleRotatingProxySubscriber\Random\RandomizerInterface;
use paslandau\GuzzleRotatingProxySubscriber\Interval\RandomTimeInterval;
use paslandau\GuzzleRotatingProxySubscriber\Interval\TimeProviderInterface;

include_once __DIR__ . "/../RandomAndTimeHelper.php";

class RandomCounterIntervalTest extends PHPUnit_Framework_TestCase
{
    private function getHelper(array $numbers){
        $randomMock = $this->getMock(RandomizerInterface::class);
        $h = new RandomAndTimeHelper($numbers, null, null, $randomMock);
        $randomMock->expects($this->any())->method("randNum")->will($this->returnCallback($h->getGetRandomNumberFn()));
        return $h;
    }

    public function test_ShouldBeReadyWhenCounterExceedsMaximumAndSetCounterToZeroOnRestart()
    {
        $numbers = [4, 6];

        $h = $this->getHelper($numbers);
        $getLastNumber = $h->getGetLastNumberFn();

        $interval = new RandomCounterInterval(0, 15, $h->getRandomMock());
        //counter is 0
        $counter = $interval->getCounter();
        $this->assertEquals(0, $counter, "Counter should be 0 but is $counter");
        $actual = $interval->isReady(); // takes first number '4' from $numbers, counter is 0 at this time
        $expected = false;
        $lastNum = $getLastNumber();
        $this->assertEquals($expected, $actual, "Should be false since counter is {$counter} and current random number is {$lastNum}");
        for($i = 1; $i < $lastNum;$i++){
            $counter = $interval->incrementCounter();
            $this->assertEquals($i, $counter, "Counter should be $i but is $counter");
            $actual = $interval->isReady();
            $this->assertEquals($expected, $actual, "Should be false since counter is {$counter} and current random number is {$lastNum}");
        }
        $counter = $interval->incrementCounter();
        $actual = $interval->isReady();
        $expected = true;
        $this->assertEquals($expected, $actual, "Should be true since counter is {$counter} and current random number is {$lastNum}");

        $interval->restart();
        $counter = $interval->getCounter();
        $this->assertEquals(0, $counter, "Counter should be 0 after restart but is $counter");
        $actual = $interval->isReady(); // takes second number '6' from $numbers, counter is 0 at this time
        $expected = false;
        $lastNum = $getLastNumber();
        $this->assertEquals($expected, $actual, "Should be false since counter is {$counter} and current random number is {$lastNum}");
    }
}
 