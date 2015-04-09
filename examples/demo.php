<?php

use GuzzleHttp\Client;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Subscriber\Mock;
use paslandau\GuzzleRotatingProxySubscriber\Proxy\RotatingProxy;
use paslandau\GuzzleRotatingProxySubscriber\ProxyRotator;
use paslandau\GuzzleRotatingProxySubscriber\RotatingProxySubscriber;

require_once __DIR__ . '/bootstrap.php';

// define proxies
$proxy1 = new RotatingProxy("username:password@111.111.111.111:4711");
$proxy2 = new RotatingProxy("username:password@112.112.112.112:4711");

// setup and attach subscriber
$rotator = new ProxyRotator([$proxy1, $proxy2]);
$sub = new RotatingProxySubscriber($rotator);
$client = new Client();
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
    $request = $client->createRequest("GET", $url);
    try {
        $response = $client->send($request);
        echo "Success with " . $request->getConfig()->get("proxy") . " on $i. request\n";
    } catch (Exception $e) {
        echo "Failed with " . $request->getConfig()->get("proxy") . " on $i. request: " . $e->getMessage() . "\n";
    }
}

/** @var \paslandau\GuzzleRotatingProxySubscriber\Proxy\RotatingProxy $proxy */
$proxies = $rotator->getProxies();
echo "\nProxy usage:\n";
foreach($proxies as $proxy){
    echo $proxy->getProxyString()."\t made ".$proxy->getTotalRequests()." requests in total\n";
}