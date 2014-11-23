<?php
use GuzzleHttp\Client;
use paslandau\GuzzleRotatingProxySubscriber\Events\WaitingEvent;
use paslandau\GuzzleRotatingProxySubscriber\Exceptions\NoProxiesLeftException;
use paslandau\GuzzleRotatingProxySubscriber\Proxy\RotatingProxyInterface;
use paslandau\GuzzleRotatingProxySubscriber\ProxyRotator;

class ProxyRotatorTest extends PHPUnit_Framework_TestCase {

    /**
     * @param $proxyString
     * @param $isUsable
     * @param int $waitingTime [optional]. Default: 0.
     * @return \paslandau\GuzzleRotatingProxySubscriber\Proxy\RotatingProxyInterface
     */
    private function getRotatingProxyMock($proxyString, $isUsable, $waitingTime
        = 0){
        $proxyMock = $this->getMock(RotatingProxyInterface::class);
        $proxyMock->expects($this->any())->method("getProxyString")->will($this->returnValue($proxyString));
        $proxyMock->expects($this->any())->method("isUsable")->will($this->returnValue($isUsable));

        $curWaitingTime = $waitingTime;
        $getWaitingTime = function ()use(&$curWaitingTime){
            return $curWaitingTime;
        };
        $calcWaitingTime = function () use(&$curWaitingTime){
          return $curWaitingTime > 0;
        };
        $resetWaitingTime = function () use(&$curWaitingTime){
            $curWaitingTime = 0;
        };
        $proxyMock->expects($this->any())->method("hasToWait")->will($this->returnCallback($calcWaitingTime));
        $proxyMock->expects($this->any())->method("getWaitingTime")->will($this->returnCallback($getWaitingTime));
        $proxyMock->expects($this->any())->method("skipWaitingTime")->will($this->returnCallback($resetWaitingTime));
        return $proxyMock;
    }

    public function test_ShouldSetupProxyOnRequestIfProxyIsReady()
    {
        $failingMock = $this->getRotatingProxyMock("foo",false);
        $proxyMock = $this->getRotatingProxyMock("test",true);
        $proxies = [$failingMock, $proxyMock];
        $useOwnIp = false;
        $rotator = new ProxyRotator($proxies, $useOwnIp);

        $client = new Client();
        $request = $client->createRequest("GET", "/");

        $isWorkingProxyLeft = $rotator->setupRequest($request);
        $proxyString = $request->getConfig()->get("proxy");
        $this->assertEquals($proxyMock->getProxyString(),$proxyString,"Expected proxy wasn't set");
        $this->assertTrue($isWorkingProxyLeft,"setupRequest should have returned true when no NullProxy is used");
    }

    public function test_ShouldSetupNullProxyOnRequestIfNoProxyIsReady()
    {
        $proxyMock = $this->getRotatingProxyMock("test",false);
        $proxies = [$proxyMock];
        $useOwnIp = true;
        $rotator = new ProxyRotator($proxies, $useOwnIp);

        $client = new Client();
        $request = $client->createRequest("GET", "/");

        $isWorkingProxyLeft = $rotator->setupRequest($request);
        $proxyString = $request->getConfig()->get("proxy");
        $this->assertEquals(null,$proxyString,"Proxy should be not set (null)");
        $this->assertFalse($isWorkingProxyLeft,"setupRequest should have returned false when the NullProxy is used");

    }

    public function test_ShouldThrowExceptionWhileSetupRequestIfNoProxyIsReady()
    {
        $this->setExpectedException(NoProxiesLeftException::class);
        $proxyMock = $this->getRotatingProxyMock("test",false);
        $proxies = [$proxyMock];
        $useOwnIp = false;
        $rotator = new ProxyRotator($proxies, $useOwnIp);

        $client = new Client();
        $request = $client->createRequest("GET", "/");

        $rotator->setupRequest($request);
    }

    public function test_ShouldGetWaitEventWithCorrectProxy(){
        $waitingTime = 5;
        $proxyMock = $this->getRotatingProxyMock("test",true, $waitingTime);
        $proxies = [$proxyMock];
        $useOwnIp = true;
        $rotator = new ProxyRotator($proxies, $useOwnIp);

        // prepare the event listener
        $eventProxy = null;
        $checkWaitingTime = function(WaitingEvent $event) use (&$eventProxy){
            $proxy = $event->getProxy();
            $eventProxy = $proxy;
            $event->skipWaiting();
        };
        $rotator->getEmitter()->on(ProxyRotator::EVENT_ON_WAIT, $checkWaitingTime);

        $client = new Client();
        $request = $client->createRequest("GET", "/");

        $rotator->setupRequest($request);

        $this->assertEquals($proxyMock,$eventProxy,"Did not get the correct proxy from the WaitingEvent");
    }

    public function test_ShouldGetNoWaitEventOnNonWaitingProxy(){
        $waitingTime = 0;
        $proxyMock = $this->getRotatingProxyMock("test",true, $waitingTime);
        $proxies = [$proxyMock];
        $useOwnIp = true;
        $rotator = new ProxyRotator($proxies, $useOwnIp);

        // prepare the event listener
        $eventProxy = null;
        $checkWaitingTime = function(WaitingEvent $event) use (&$eventProxy){
            $proxy = $event->getProxy();
            $eventProxy = $proxy;
            $event->skipWaiting();
        };
        $rotator->getEmitter()->on(ProxyRotator::EVENT_ON_WAIT, $checkWaitingTime);

        $client = new Client();
        $request = $client->createRequest("GET", "/");

        $rotator->setupRequest($request);

        $this->assertEquals(null,$eventProxy,"Did not get the correct proxy from the WaitingEvent");
    }
}
 