<?php

use GuzzleHttp\Client;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Subscriber\Mock;
use paslandau\GuzzleRotatingProxySubscriber\Exceptions\NoProxiesLeftException;
use paslandau\GuzzleRotatingProxySubscriber\Proxy\RotatingProxy;
use paslandau\GuzzleRotatingProxySubscriber\ProxyRotator;
use paslandau\GuzzleRotatingProxySubscriber\RotatingProxySubscriber;

require_once __DIR__ . '/bootstrap.php';

$proxy1 = new RotatingProxy("username:password@111.111.111.111:4711");
$proxy2 = new RotatingProxy("username:password@112.112.112.112:4711");
$proxy3 = new RotatingProxy("username:password@113.113.113.113:4711");
$proxies = [$proxy1,$proxy2,$proxy3];
$rotator = new ProxyRotator($proxies);
$sub = new RotatingProxySubscriber($rotator);
$client = new Client(["defaults" => ["headers" => ["User-Agent" => null]]]); // remove User-Agent info from request
$client->getEmitter()->attach($sub);

// lets prepare 20 responses
$num = 20;
$responses = [];
for($i = 0; $i < $num; $i++){
    $responses[] = new Response(200);
}
$mock = new Mock($responses);
$client->getEmitter()->attach($mock);

// lets execute 20 requests
$requests = [];
$url = "http://localhost/";
for ($i = 0; $i < $num; $i++) {
    $request =  $client->createRequest("GET",$url);
    try {
        $response = $client->send($request);
        echo "Success with " . $request->getConfig()->get("proxy") . " on $i. request\n";
    } catch (Exception $e) {
        if ($e->getPrevious() instanceof NoProxiesLeftException) {
            echo "All proxies are blocked, terminating...\n";
            break;
        }
        echo "Failed with " . $request->getConfig()->get("proxy") . " on $i. request: " . $e->getMessage() . "\n";
    }
}

/** @var \paslandau\GuzzleRotatingProxySubscriber\Proxy\RotatingProxy $proxy */
echo "\nProxy usage:\n";
foreach($proxies as $proxy){
    echo $proxy->getProxyString()."\t made ".$proxy->getTotalRequests()." requests in total\n";
}

