<?php

use GuzzleHttp\Client;
use paslandau\GuzzleRotatingProxySubscriber\Proxy\RotatingProxy;
use paslandau\GuzzleRotatingProxySubscriber\ProxyRotator;
use paslandau\GuzzleRotatingProxySubscriber\RotatingProxySubscriber;

require_once __DIR__ . '/demo-bootstrap.php';

// define proxies
$proxy1 = new RotatingProxy("username:password@111.111.111.111:4711");
$proxy2 = new RotatingProxy("username:password@112.112.112.112:4711");

// setup and attach subscriber
$rotator = new ProxyRotator([$proxy1, $proxy2]);
$sub = new RotatingProxySubscriber($rotator);
$client = new Client();
$client->getEmitter()->attach($sub);

// perform the requests
$num = 10;
$url = "http://www.myseosolution.de/scripts/myip.php";
for ($i = 0; $i < $num; $i++) {
    $request = $client->createRequest("GET", $url);
    try {
        $response = $client->send($request);
        echo "Success with " . $request->getConfig()->get("proxy") . " on $i. request\n";
    } catch (Exception $e) {
        echo "Failed with " . $request->getConfig()->get("proxy") . " on $i. request: " . $e->getMessage() . "\n";
    }
}