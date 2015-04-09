<?php
use GuzzleHttp\Client;
use GuzzleHttp\Collection;
use GuzzleHttp\Cookie\CookieJarInterface;
use GuzzleHttp\Event\AbstractTransferEvent;
use GuzzleHttp\Event\CompleteEvent;
use GuzzleHttp\Event\EventInterface;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Subscriber\Cookie;
use paslandau\GuzzleRotatingProxySubscriber\Interval\NullRandomCounter;
use paslandau\GuzzleRotatingProxySubscriber\Interval\RandomCounterIntervalInterface;
use paslandau\GuzzleRotatingProxySubscriber\Proxy\Identity;
use paslandau\GuzzleRotatingProxySubscriber\Proxy\IdentityInterface;
use paslandau\GuzzleRotatingProxySubscriber\Proxy\RotatingIdentityProxy;
use paslandau\GuzzleRotatingProxySubscriber\Random\RandomizerInterface;

class RotatingIdentityProxyTest extends PHPUnit_Framework_TestCase
{

    public function test_ShouldNotAllowEmptyIdentitiesInConstructor()
    {
        $this->setExpectedException(\InvalidArgumentException::class);
        new RotatingIdentityProxy([], "");
    }

    public function test_ShouldIncrementCounterAfterEachRequest(){
        /** @var  RandomizerInterface|PHPUnit_Framework_MockObject_MockObject $randomizer */
        $randomizer = $this->getMock(RandomizerInterface::class);

        /** @var  RandomCounterIntervalInterface|PHPUnit_Framework_MockObject_MockObject $counter */
        $counter = $this->getMock(RandomCounterIntervalInterface::class);
        $counter->expects($this->once())->method("incrementCounter");

        /** @var  IdentityInterface|PHPUnit_Framework_MockObject_MockObject $identity */
        $identity = $this->getMock(IdentityInterface::class);
        $rip = new RotatingIdentityProxy([$identity], "", $randomizer, $counter);
        $rip->requested();
    }

    public function test_ShouldChooseIdentityAfterInstantiationAndSetupRequestAccordingly()
    {
        $identityKey = 0;
        /** @var  RandomizerInterface|PHPUnit_Framework_MockObject_MockObject $randomizer */
        $randomizer = $this->getMock(RandomizerInterface::class);
        $randomizer->expects($this->any())->method("randKey")->willReturn($identityKey);

        $randomCounter = new NullRandomCounter();

        $proxyString = "proxy";

        /** @var CookieJarInterface|PHPUnit_Framework_MockObject_MockObject $jarMock */
        $jarMock = $this->getMock(CookieJarInterface::class);

        $userAgent = "foo";
        $headers =  ["test" => "baz"];
        $identity = new Identity($userAgent, $headers, $jarMock);
        $identities = [
            $identityKey => $identity
        ];
        $rip = new RotatingIdentityProxy($identities, $proxyString, $randomizer, $randomCounter);
        $actual = $rip->getCurrentIdentity();
        $this->assertSame($identity, $actual, "Got wrong identity after object instantiation");

        //setup request
        $client = new Client();
        $request = $client->createRequest("GET", "/");
        $request = $rip->setupRequest($request);

        $actualUserAgent = $request->getHeader("user-agent");
        $this->assertEquals($userAgent,$actualUserAgent,"Expected header 'user-agent' to be {$userAgent} - it was {$actualUserAgent} instead");
        foreach($headers as $key => $val){
            $this->assertTrue($request->hasHeader($key),"Expected header '{$key}' was not present");
            $acutalHeader = $request->getHeader($key);
            $this->assertEquals($val,$acutalHeader,"Expected header '{$key}' to be {$val} - it was {$acutalHeader} instead");
        }
        $emitter = $request->getEmitter();
        $actualCookieJar = null;
        foreach($emitter->listeners("complete") as $listener){
            /** @var Cookie[] $listener  */
            if(is_array($listener) && $listener[0] instanceof Cookie) {
                $actualCookieJar = $listener[0]->getCookieJar();
            }
        }
        $this->assertSame($jarMock, $actualCookieJar, "Got wrong cookie jar after request setup");
    }

    public function test_ShouldSwitchIdentities()
    {
        /** @var  RandomizerInterface|PHPUnit_Framework_MockObject_MockObject $randomizer */
        $randomizer = $this->getMock(RandomizerInterface::class);
        $keys = [1,2,0]; // order identity keys
        $checkKeys = $keys;
        $getKeysFn = function($arr) use (&$keys){
            return array_shift($keys);
        };
        $randomizer->expects($this->any())->method("randKey")->willReturnCallback($getKeysFn);

        $randomCounter = new NullRandomCounter();

        $proxyString = "proxy";

        /** @var CookieJarInterface|PHPUnit_Framework_MockObject_MockObject $jarMock */
        $jarMock = $this->getMock(CookieJarInterface::class);

        $identities = [
            0 => new Identity("0"),
            1 => new Identity("1"),
            2 => new Identity("2"),
        ];
        $rip = new RotatingIdentityProxy($identities, $proxyString, $randomizer, $randomCounter);
        foreach($checkKeys as $key) {
            $rip->switchIdentity();
            $expected = $identities[$key];
            $actual = $rip->getCurrentIdentity();
            $msg = "Got wrong identity ({$actual->getUserAgent()}) after object instantiation, expected $key";
            $this->assertSame($expected, $actual, $msg);
        }
    }

    public function test_ShouldSetReferrerOnIdentity()
    {
        $identityKey = 0;
        $referer = "foo";
        /** @var  RandomizerInterface|PHPUnit_Framework_MockObject_MockObject $randomizer */
        $randomizer = $this->getMock(RandomizerInterface::class);
        $randomizer->expects($this->any())->method("randKey")->willReturn($identityKey);

        $randomCounter = new NullRandomCounter();

        $proxyString = "proxy";

        /** @var RequestInterface|PHPUnit_Framework_MockObject_MockObject $requestMock */
        $requestMock = $this->getMock(RequestInterface::class);
        $collection = new Collection();
        $requestMock->expects($this->any())->method("getConfig")->willReturn($collection);

        /** @var ResponseInterface|PHPUnit_Framework_MockObject_MockObject $responseMock */
        $responseMock = $this->getMock(ResponseInterface::class);
        $responseMock->expects($this->once())->method("getEffectiveUrl")->willReturn($referer);


        /** @var AbstractTransferEvent|PHPUnit_Framework_MockObject_MockObject $eventMock */
        $eventMock = $this->getMock(AbstractTransferEvent::class,[],[],"",false);
        $eventMock->expects($this->once())->method("getResponse")->willReturn($responseMock);

        /** @var IdentityInterface|PHPUnit_Framework_MockObject_MockObject $identityMock */
        $identityMock = $this->getMock(IdentityInterface::class);
        $identityMock->expects($this->once())->method("setReferer");
        $identityMock->expects($this->atLeast(1))->method("getReferer");


        $identities = [
            $identityKey => $identityMock
        ];
        $rip = new RotatingIdentityProxy($identities, $proxyString, $randomizer, $randomCounter);
        $rip->evaluate($eventMock); // call setReferer
        $rip->setupRequest($requestMock); // call getReferer at least once
    }

}