<?php
use GuzzleHttp\Client;
use GuzzleHttp\Event\AbstractTransferEvent;
use GuzzleHttp\Event\CompleteEvent;
use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Pool;
use GuzzleHttp\Subscriber\Mock;
use paslandau\GuzzleRotatingProxySubscriber\Proxy\RotatingProxy;
use paslandau\GuzzleRotatingProxySubscriber\ProxyRotator;
use paslandau\GuzzleRotatingProxySubscriber\Random\RandomizerInterface;
use paslandau\GuzzleRotatingProxySubscriber\RotatingProxySubscriber;
use paslandau\GuzzleRotatingProxySubscriber\Time\RandomTimeInterval;
use paslandau\GuzzleRotatingProxySubscriber\Time\TimeProviderInterface;

include_once __DIR__ . "/../RandomAndTimeHelper.php";

class RotatingProxySubscriberTest extends PHPUnit_Framework_TestCase {

    private function getHelper(array $numbers = null, array $times = null, array $randKeys = null){
        $randomMock = $this->getMock(RandomizerInterface::class);
        $timeMock = $this->getMock(TimeProviderInterface::class);
        $h = new RandomAndTimeHelper($numbers, $times, $randKeys, $randomMock,$timeMock);
        $randomMock->expects($this->any())->method("randNum")->will($this->returnCallback($h->getGetRandomNumberFn()));
        $randomMock->expects($this->any())->method("randKey")->will($this->returnCallback($h->getGetRandomKeyFn()));
        $timeMock->expects($this->any())->method("getTime")->will($this->returnCallback($h->getGetRandomTimeFn()));

        return $h;
    }

    public function test_integration_ChooseTheRightProxyAtTheRightTime()
    {
        /*
         * Scenario:
         * 10 requests, 2 proxies
         * In the end,
         * proxy_0 should have 6 total requests and 1 total error and 0 consecutive errors
         * proxy_1 should have 4 successful requests and 3 total and consecutive errors (making it fail)
         * No retries take place
         */
        $total_0 = 6;
        $total_error_0 = 1;
        $consecutive_error_0 = 0;
        $max_consecutive_error_0 = 3;
        $total_1 = 4;
        $total_error_1 = 3;
        $consecutive_error_1 = 3;
        $max_consecutive_error_1 = 3;
        $client = new Client();

        $proxy0 = new RotatingProxy("0", null, $max_consecutive_error_0, 10, null);
        $proxy1 = new RotatingProxy("1", null, $max_consecutive_error_1, 10, null);
        $proxies = [
            0 => $proxy0,
            1 => $proxy1
        ];

        $success = true;
        $fail = false;
        $responses2Proxy = [
            [$success, $proxy0],
            [$success, $proxy0],
            [$success, $proxy1],
            [$fail, $proxy1],
            [$fail, $proxy0],
            [$fail, $proxy1],
            [$fail, $proxy1],
            [$success, $proxy0],
            [$success, $proxy0],
            [$success, $proxy0],
        ];
        $randKeys = [];
        $responses = [];
        foreach ($responses2Proxy as $key => $val) {
            $randKeys[$key] = array_search($val[1],$proxies);
            $responses[$key] = ($val[0])?new Response(200):new Response(403);
        }

        $h = $this->getHelper(null, null, $randKeys);
        $useOwnIp = false;

        $rotator = new ProxyRotator($proxies, $useOwnIp, $h->getRandomMock());
        $sub = new RotatingProxySubscriber($rotator);

        $mock = new Mock($responses);

        // Add the mock subscriber to the client.
        $client->getEmitter()->attach($mock);
        $client->getEmitter()->attach($sub);

        // build requests - we need to do this _after_ the $mock hast been attached to the client,
        // otherwise a real request is sent.
        $requests = [];
        foreach ($responses as $key => $val) {
            $req = $client->createRequest("GET");
            $req->getConfig()->set("request_id", $key);
            $requests[$key] = $req;
        }

//        $sucFn = function(RequestInterface $request){
//            echo "Success at request ".$request->getConfig()->get("request_id")." using proxy ".$request->getConfig()->get("proxy")."\n";
//        };
//        $errFn = function(RequestInterface $request, Exception $e){
//            echo "Error at request ".$request->getConfig()->get("request_id")." using proxy ".$request->getConfig()->get("proxy").": ".$e->getMessage()."\n";
//        };
//        foreach($requests as $key => $request){
//            try {
//                $client->send($request);
//                $sucFn($request);
//            }catch(Exception $e){
//                $errFn($request, $e);
//            }
//        }

        $options = [
//            "complete" => function (CompleteEvent $ev) use ($sucFn) { $sucFn($ev->getRequest());},
//            "error" => function (ErrorEvent $ev) use ($errFn) { $errFn($ev->getRequest(),$ev->getException());},
        ];
        $pool = new Pool($client,$requests,$options);
        $pool->wait();

        $this->assertEquals($total_0, $proxy0->getTotalRequests());
        $this->assertEquals($total_error_0, $proxy0->getCurrentTotalFails());
        $this->assertEquals($consecutive_error_0, $proxy0->getCurrentConsecutiveFails());
        $this->assertEquals($consecutive_error_0 < $max_consecutive_error_0, $proxy0->isUsable());
        $this->assertEquals($total_1, $proxy1->getTotalRequests());
        $this->assertEquals($total_error_1, $proxy1->getCurrentTotalFails());
        $this->assertEquals($consecutive_error_1, $proxy1->getCurrentConsecutiveFails());
        $this->assertEquals($consecutive_error_1 < $max_consecutive_error_1, $proxy1->isUsable());
    }

    public function test_integration_ShouldHonorWaitingTimes()
    {
        /*
         * Scenario:
         * 7 requests, 3 proxies
         * 1 - proxy_0 => successful request
         * 2 - proxy_1 => successful request
         * 3 - proxy_2 => successful request
         * 4 - proxy_0 => has to wait 5 seconds
         *   - proxy_2 => successful request
         * 5 - proxy_1 => has to wait 24 seconds
         *   - proxy_2 => has to wait 1 seconds
         *   - proxy_0 => failed request
         * 6 - proxy_2 => successful request
         * 7 - proxy_2 => has to wait 5 seconds
         *   - proxy_1 => has to wait 4 seconds
         *   - proxy_0 => has to wait 3 seconds
         * // sleep for 3 seconds - test by event
         *   - proxy_1 => has to wait 1 seconds
         *   - proxy_2 => has to wait 2 seconds
         *   - proxy_0 => successful request
         */

        $client = new Client();

        $numbers = [
            10, // request 4, picked at hasToWait (should return true)
            0,  // request 5, picked at hasToWait (should return false)
            10,  // request 7, picked at hasToWait (should return true)
        ];
        $times = [
            5,  // after request 1, picked at restartWaitingTime -- 5 = lastActionTime
            10, // request 4, picked at hasToWait (true)
            10, // request 4, picked at getWaitingTime (should return 5 - (10 - 10) => 5)
            20, // request 5, picked at hasToWait (should return false)
            23, // after request 5, picked at restartWaitingTime -- 23 = lastActionTime
            30, // request 7, picked at hasToWait (should return true)
            30, // request 7, picked at getWaitingTime (should return 23 - (30 - 10) => 3)
            30, // request 7, picked at getWaitingTime in sleep-loop (should return 23 - (30 - 10) => 3)
            35, // request 7, picked at hasToWait (should return false)
        ];
        $h = $this->getHelper($numbers,$times);
        $interval = new RandomTimeInterval(0, 15, $h->getRandomMock(), $h->getTimeMock());
        $proxy0 = new RotatingProxy("0", null, 5, 10, $interval);


        $numbers = [
            29,  // request 5, picked at hasToWait (should return true)
        ];
        $times = [
            5,  // after request 2, picked at restartWaitingTime -- 5 = lastActionTime
            10, // request 5, picked at hasToWait (true)
            10, // request 5, picked at getWaitingTime (should return 5 - (10 - 29) => 24)
            30, // request 7, picked at hasToWait (should return true)
            30, // request 7, picked at getWaitingTime (should return 5 - (30 - 29) => 4)
            33, // request 7, picked at hasToWait (should return true)
            33, // request 7, picked at getWaitingTime (should return 5 - (33 - 29) => 1)
        ];
        $h = $this->getHelper($numbers,$times);
        $interval = new RandomTimeInterval(0, 15, $h->getRandomMock(), $h->getTimeMock());
        $proxy1 = new RotatingProxy("1", null, 5, 10, $interval);

        $numbers = [
            5, // request 4, picked at hasToWait (should return false)
            8,  // request 5, picked at hasToWait (should return true)
            10,  // request 7, picked at hasToWait (should return false)
        ];
        $times = [
            5,  // after request 3, picked at restartWaitingTime -- 5 = lastActionTime
            10, // request 4, picked at hasToWait (false)
            13, // after request 4, picked at restartWaitingTime -- 13 = lastActionTime
            20, // request 5, picked at hasToWait (should return true)
            20, // request 5, picked at getWaitingTime (should return 13 - (20 - 8) => 1)
            25, // request 6, picked at hasToWait (should return true)
            25, // after request 6, picked at restartWaitingTime -- 25 = lastActionTime
            30, // request 7, picked at hasToWait (should return true)
            30, // request 7, picked at getWaitingTime (should return 25 - (30 - 10) => 5)
            33, // request 7, picked at hasToWait (should return true)
            33, // request 7, picked at getWaitingTime (should return 25 - (33 - 10) => 2)
        ];
        $h = $this->getHelper($numbers,$times);
        $interval = new RandomTimeInterval(0, 15, $h->getRandomMock(), $h->getTimeMock());
        $proxy2 = new RotatingProxy("2", null, 5, 10, $interval);

        $proxies = [
            $proxy0->getProxyString() => $proxy0,
            $proxy1->getProxyString() => $proxy1,
            $proxy2->getProxyString() => $proxy2,
        ];

        $success = true;
        $fail = false;

        $responses2Proxy = [
            [$success, $proxy0], //1
            [$success, $proxy1], //2
            [$success, $proxy2], //3
            [null, $proxy0],     //4
            [$success, $proxy2],
            [null, $proxy1],    //5
            [null, $proxy2],    //5
            [$fail, $proxy0],   //5
            [$success, $proxy2],//6
            [null, $proxy2],    //7
            [null, $proxy1],
            [null, $proxy0],
            [null, $proxy1],
            [null, $proxy2],
            [$success, $proxy0],
        ];
        $randKeys = [];
        $responses = [];
        foreach ($responses2Proxy as $key => $val) {
            $randKeys[] = array_search($val[1],$proxies);
            if($val[0] !== null) {
                $responses[] = ($val[0]) ? new Response(200) : new Response(403);
            }
        }

        $h = $this->getHelper(null, null, $randKeys);
        $useOwnIp = false;

        $rotator = new ProxyRotator($proxies, $useOwnIp, $h->getRandomMock());
        $sub = new RotatingProxySubscriber($rotator);

        $mock = new Mock($responses);

        // Add the mock subscriber to the client.
        $client->getEmitter()->attach($mock);
        $client->getEmitter()->attach($sub);

        // build requests - we need to do this _after_ the $mock hast been attached to the client,
        // otherwise a real request is sent.
        $requests = [];
        foreach ($responses as $key => $val) {
            $req = $client->createRequest("GET");
            $req->getConfig()->set("request_id", $key);
            $requests[$key] = $req;
        }


        $checkState = function($curProxy) use (&$responses2Proxy){
            while(count($responses2Proxy) > 0){
                $el = array_shift($responses2Proxy);
                if($el === null){
                    break;
                }
                if($el[0] !== null) {
                    $this->assertEquals($curProxy, $el[1]->getProxyString());
                    break;
                }
            };
        };

        $sucFn = function(RequestInterface $request) use ($checkState){
            $proxy = $request->getConfig()->get("proxy");
            $checkState($proxy);
//            echo "Success at request ".($request->getConfig()->get("request_id")+1)." using proxy ".$proxy."\n";
        };
        $errFn = function(RequestInterface $request, Exception $e) use ($checkState){
            $proxy = $request->getConfig()->get("proxy");
            $checkState($proxy);
//            echo "Error at request ".($request->getConfig()->get("request_id")+1)." using proxy ".$proxy.": ".$e->getMessage()."\n";
        };

        $options = [
            "complete" => function (CompleteEvent $ev) use ($sucFn) { $sucFn($ev->getRequest());},
            "error" => function (ErrorEvent $ev) use ($errFn) { $errFn($ev->getRequest(),$ev->getException());},
        ];
        $pool = new Pool($client,$requests,$options);
        $pool->wait();
    }
}
 