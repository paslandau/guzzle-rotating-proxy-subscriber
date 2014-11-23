<?php

use GuzzleHttp\Client;
use GuzzleHttp\Event\EndEvent;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Pool;
use paslandau\GuzzleRotatingProxySubscriber\Exceptions\NoProxiesLeftException;
use paslandau\GuzzleRotatingProxySubscriber\Proxy\RotatingProxy;
use paslandau\GuzzleRotatingProxySubscriber\ProxyRotator;
use paslandau\GuzzleRotatingProxySubscriber\RotatingProxySubscriber;

require_once __DIR__ . '/demo-bootstrap.php';

$proxy1 = new RotatingProxy("username:password@111.111.111.111:4711");
$proxy2 = new RotatingProxy("username:password@112.112.112.112:4711");
$proxy3 = new RotatingProxy("username:password@113.113.113.113:4711");
$proxies = [$proxy1,$proxy2,$proxy3];
$rotator = new ProxyRotator($proxies);
$sub = new RotatingProxySubscriber($rotator);
$client = new Client(["defaults" => ["headers" => ["User-Agent" => null]]]);
$client->getEmitter()->attach($sub);

$num = 10;
$requests = [];
$url = "http://www.myseosolution.de/scripts/myip.php";
for ($i = 0; $i < $num; $i++) {
    $req = $client->createRequest("GET", $url);
    $req->getConfig()->set("id", $i);
    $requests[] = $req;
}

$completeFn = function(Pool $pool, RequestInterface $request, ResponseInterface $response){
    echo "Success with " . $request->getConfig()->get("proxy") . " on {$request->getConfig()->get("id")}. request\n";
};
$errorFn = function(Pool $pool, RequestInterface $request, ResponseInterface $response = null, Exception $exception){
    if($exception instanceof NoProxiesLeftException){
        echo "All proxies are blocked, terminating...\n";
        $pool->cancel();
    }else {
        echo "Failed with " . $request->getConfig()->get("proxy") . " on {$request->getConfig()->get("id")}. request: " . $exception->getMessage() . "\n";
    }
};

$pool = new Pool($client, $requests, [
    "pool_size" => 3,
    "end" => function (EndEvent $event) use(&$pool, $completeFn, $errorFn) {
        $request = $event->getRequest();
        $response = $event->getResponse();
        $exception = $event->getException();
        if($exception === null){
            $completeFn($pool, $request, $response);
        }else{
            $errorFn($pool, $request, $response, $exception);
        }
    }
]);
$pool->wait();
/** @var \paslandau\GuzzleRotatingProxySubscriber\Proxy\RotatingProxy $proxy */
foreach($proxies as $proxy){
    echo $proxy->getProxyString()."\t made ".$proxy->getTotalRequests()." requests in total\n";
}