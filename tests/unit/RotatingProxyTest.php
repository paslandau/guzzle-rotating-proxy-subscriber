<?php
use paslandau\GuzzleRotatingProxySubscriber\Proxy\RotatingProxy;
use paslandau\GuzzleRotatingProxySubscriber\Interval\TimeIntervalInterface;

class RotatingProxyTest extends PHPUnit_Framework_TestCase
{

    public function test_ShouldFailOnTooManyTotalFailsAndNotBefore()
    {
        $maxFails = 10;
        $rp = new RotatingProxy("test", null, -1, $maxFails, null);
        for ($i = 0; $i < $maxFails - 1; $i++) {
            $rp->failed();
        }
        $this->assertFalse($rp->hasTooManyTotalFails(), "Expected NOT to have enough total fails");
        $rp->failed();
        $this->assertTrue($rp->hasTooManyTotalFails(), "Expected to have enough total fails");

        $rp->setMaxTotalFails(-1);
        $this->assertFalse($rp->hasTooManyTotalFails(), "Expected NOT to have enough total fails since it inifite fails should be allowed");
    }

    public function test_ShouldFailOnTooManyConsecutiveFailsAndNotBefore()
    {
        $maxFails = 10;
        $rp = new RotatingProxy("test", null, $maxFails, -1, null);
        for ($i = 0; $i < $maxFails - 1; $i++) {
            $rp->failed();
        }
        $this->assertFalse($rp->hasTooManyConsecutiveFails(), "Expected NOT to have enough consecutive fails");
        $rp->failed();
        $this->assertTrue($rp->hasTooManyConsecutiveFails(), "Expected to have enough consecutive fails");

        $rp->setMaxConsecutiveFails(-1);
        $this->assertFalse($rp->hasTooManyTotalFails(), "Expected NOT to have enough consecutive fails since it inifite fails should be allowed");

        $rp->setMaxConsecutiveFails($maxFails);
        $rp->setCurrentConsecutiveFails(0);
        for ($i = 0; $i < $maxFails - 1; $i++) {
            $rp->failed();
        }
        $this->assertFalse($rp->hasTooManyConsecutiveFails("Expected NOT to have enough consecutive fails after resetting"));
        $rp->succeeded();
        $rp->failed();
        $this->assertFalse($rp->hasTooManyConsecutiveFails("Expected NOT to have enough consecutive fails after succeeding"));
    }

    public function test_ShouldBeUnsuableOnTooManyFailsOrIfBlocked()
    {
        $maxFails = 10;
        $rp = new RotatingProxy("test", null, $maxFails, -1, null);
        $this->assertTrue($rp->isUsable(), "Expected NOT to have enough consecutive fails");
        $rp->setCurrentConsecutiveFails($maxFails);
        $this->assertFalse($rp->isUsable(), "Expected to have enough consecutive fails");
        $rp->setCurrentConsecutiveFails(0);
        $rp->block();
        $this->assertFalse($rp->isUsable(), "Expected to be blocked");
        $rp->unblock();
        $this->assertTrue($rp->isUsable(), "Expected NOT to be blocked");
    }

    public function test_ShouldNotWaitIfReady()
    {
        $timeMock = $this->getMock(TimeIntervalInterface::class);

        $waitTime = 0;
        $timeMock->expects($this->any())->method("isReady")->will($this->returnValue(true));
        $timeMock->expects($this->any())->method("getWaitingTime")->will($this->returnValue($waitTime));

        /** @var TimeIntervalInterface $timeMock */
        $rp = new RotatingProxy("test", null, -1, -1, $timeMock);
        $res = $rp->hasToWait();
        $this->assertFalse($res, "Expected proxy does NOT need to wait");
        $this->assertEquals($waitTime, $rp->getWaitingTime(), "Expected $waitTime seconds to wait");
    }

    public function test_ShouldWaitIfNotReady()
    {
        $waitTime = 5;
        $timeMock2 = $this->getMock(TimeIntervalInterface::class);

        $timeMock2->expects($this->any())->method("isReady")->will($this->returnValue(false));
        $timeMock2->expects($this->any())->method("getWaitingTime")->will($this->returnValue($waitTime));

        /** @var \paslandau\GuzzleRotatingProxySubscriber\Interval\TimeIntervalInterface $timeMock2 */
        $rp = new RotatingProxy("test", null, -1, -1, $timeMock2);
        $this->assertTrue($rp->hasToWait(), "Expected proxy needs to wait");
        $this->assertEquals($waitTime, $rp->getWaitingTime(), "Expected $waitTime seconds to wait");
    }

    public function test_ShouldCallReset()
    {
        $timeMock = $this->getMock(TimeIntervalInterface::class);
        $timeMock->expects($this->once())->method("reset");
        /** @var TimeIntervalInterface $timeMock */
        $rp = new RotatingProxy("test", null, -1, -1, $timeMock);
        $rp->skipWaitingTime();
    }

    public function test_ShouldCallRestart()
    {
        $timeMock = $this->getMock(TimeIntervalInterface::class);
        $timeMock->expects($this->once())->method("restart");
        /** @var TimeIntervalInterface $timeMock */
        $rp = new RotatingProxy("test", null, -1, -1, $timeMock);
        $rp->restartWaitingTime();
    }

}