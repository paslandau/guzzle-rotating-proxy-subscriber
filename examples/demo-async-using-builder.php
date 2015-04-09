<?php

use GuzzleHttp\Client;
use GuzzleHttp\Event\EndEvent;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Pool;
use GuzzleHttp\Subscriber\Mock;
use paslandau\GuzzleRotatingProxySubscriber\Builder\Build;
use paslandau\GuzzleRotatingProxySubscriber\Events\WaitingEvent;
use paslandau\GuzzleRotatingProxySubscriber\Exceptions\NoProxiesLeftException;
use paslandau\GuzzleRotatingProxySubscriber\ProxyRotator;
use paslandau\GuzzleRotatingProxySubscriber\RotatingProxySubscriber;

require_once __DIR__ . '/bootstrap.php';

$s = "
username:password@111.111.111.111:4711
username:password@112.112.112.112:4711
username:password@113.113.113.113:4711
";

$rotator = Build::rotator()
    ->failsIfNoProxiesAreLeft()
    ->withProxiesFromString($s, "\n")
    ->evaluatesProxyResultsByDefault()
    ->eachProxyMayFailInfinitlyInTotal()
    ->eachProxyMayFailConsecutively(3)
    ->eachProxyNeedsToWaitSecondsBetweenRequests(1, 3)
    ->build();

$getWaitingTime = function (WaitingEvent $e) {
    echo "Need to wait " . $e->getProxy()->getWaitingTime() . "s\n";
};
$rotator->getEmitter()->on(ProxyRotator::EVENT_ON_WAIT, $getWaitingTime);

$sub = new RotatingProxySubscriber($rotator);
$client = new Client(["defaults" => ["headers" => ["User-Agent" => null]]]);
$client->getEmitter()->attach($sub);

// lets prepare 10 responses
$num = 10;
$responses = [];
for($i = 0; $i < $num; $i++){
    $responses[] = new Response(200);
}
$mock = new Mock($responses);
$client->getEmitter()->attach($mock);

// lets execute 10 requests
$requests = [];
$url = "http://localhost/";
for ($i = 0; $i < $num; $i++) {
    $req = $client->createRequest("GET", $url);
    $req->getConfig()->set("id", $i);
    $requests[] = $req;
}

$completeFn = function (Pool $pool, RequestInterface $request, ResponseInterface $response) {
    echo "Success with " . $request->getConfig()->get("proxy") . " on {$request->getConfig()->get("id")}. request\n";
};
$errorFn = function (Pool $pool, RequestInterface $request, ResponseInterface $response = null, Exception $exception) {
    if ($exception instanceof NoProxiesLeftException) {
        echo "All proxies are blocked, terminating...\n";
        $pool->cancel();
    } else {
        echo "Failed with " . $request->getConfig()->get("proxy") . " on {$request->getConfig()->get("id")}. request: " . $exception->getMessage() . "\n";
    }
};

$pool = new Pool($client, $requests, [
    "pool_size" => 3,
    "end" => function (EndEvent $event) use (&$pool, $completeFn, $errorFn) {
        $request = $event->getRequest();
        $response = $event->getResponse();
        $exception = $event->getException();
        if ($exception === null) {
            $completeFn($pool, $request, $response);
        } else {
            $errorFn($pool, $request, $response, $exception);
        }
    }
]);
$pool->wait();

/** @var \paslandau\GuzzleRotatingProxySubscriber\Proxy\RotatingProxy $proxy */
$proxies = $rotator->getProxies();
echo "\nProxy usage:\n";
foreach($proxies as $proxy){
    echo $proxy->getProxyString()."\t made ".$proxy->getTotalRequests()." requests in total\n";
}