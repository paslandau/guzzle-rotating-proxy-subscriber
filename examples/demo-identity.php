<?php

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Subscriber\Mock;
use paslandau\GuzzleRotatingProxySubscriber\Exceptions\NoProxiesLeftException;
use paslandau\GuzzleRotatingProxySubscriber\Interval\RandomCounterInterval;
use paslandau\GuzzleRotatingProxySubscriber\Proxy\Identity;
use paslandau\GuzzleRotatingProxySubscriber\Proxy\RotatingIdentityProxy;
use paslandau\GuzzleRotatingProxySubscriber\Proxy\RotatingProxy;
use paslandau\GuzzleRotatingProxySubscriber\ProxyRotator;
use paslandau\GuzzleRotatingProxySubscriber\Random\SystemRandomizer;
use paslandau\GuzzleRotatingProxySubscriber\RotatingProxySubscriber;

require_once __DIR__ . '/bootstrap.php';


$userAgent = "Mozilla/5.0 (Windows NT 6.3; WOW64; rv:37.0) Gecko/20100101 Firefox/37.0"; // common user agent string for firefox
$defaultRequestHeaders = ["Accept-Language" => "de,en"]; // add a preferred language to each of our requests
$cookieSession = new CookieJar(); // enable cookies for this identity

$identity = new Identity($userAgent,$defaultRequestHeaders,$cookieSession);
$identities = [$identity];
$proxy1 = new RotatingIdentityProxy($identities, "[PROXY 1]");

$userAgent = "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36"; // common user agent string for chrome
$defaultRequestHeaders = ["Accept-Language" => "de"]; // add a preferred language to each of our requests
$cookieSession = null; // disable cookies for this identity

$identity1 = new Identity($userAgent,$defaultRequestHeaders,$cookieSession);

$userAgent = "Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; WOW64; Trident/6.0)"; // common user agent string for Internet Explorer
$defaultRequestHeaders = ["Pragma" => "no-cache"]; // add a no-cache directive to each request
$cookieSession = new CookieJar(); // enable cookies for this identity

$identity2 = new Identity($userAgent,$defaultRequestHeaders,$cookieSession);

$identities = [$identity1,$identity2];
$systemRandomizer = new SystemRandomizer();

// switch identities randomly after 2 to 5 requests
$minRequests = 2;
$maxRequests = 5;
$counter = new RandomCounterInterval($minRequests,$maxRequests);
$proxy2 = new RotatingIdentityProxy($identities, "[PROXY 2]",$systemRandomizer,$counter);

$proxies = [$proxy1,$proxy2];
$rotator = new ProxyRotator($proxies);
$sub = new RotatingProxySubscriber($rotator);
$client = new Client();
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
for($i = 0; $i < $num; $i++){
    $requests[] = $client->createRequest("GET",$url);
}

for ($i = 0; $i < $num; $i++) {
    $request =  $client->createRequest("GET",$url);
    try {
        $response = $client->send($request);
        echo "Success with " . $request->getConfig()->get("proxy") . " using user agent " . $request->getHeader("user-agent"). " on $i. request\n";
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

/** Example ouput
Success with [PROXY 2] using user agent Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; WOW64; Trident/6.0) on 0. request
Success with [PROXY 2] using user agent Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; WOW64; Trident/6.0) on 1. request
Success with [PROXY 2] using user agent Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; WOW64; Trident/6.0) on 2. request
Success with [PROXY 1] using user agent Mozilla/5.0 (Windows NT 6.3; WOW64; rv:37.0) Gecko/20100101 Firefox/37.0 on 3. request
Success with [PROXY 1] using user agent Mozilla/5.0 (Windows NT 6.3; WOW64; rv:37.0) Gecko/20100101 Firefox/37.0 on 4. request
Success with [PROXY 2] using user agent Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; WOW64; Trident/6.0) on 5. request
Success with [PROXY 2] using user agent Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; WOW64; Trident/6.0) on 6. request
Success with [PROXY 2] using user agent Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36 on 7. request
Success with [PROXY 2] using user agent Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36 on 8. request
Success with [PROXY 1] using user agent Mozilla/5.0 (Windows NT 6.3; WOW64; rv:37.0) Gecko/20100101 Firefox/37.0 on 9. request
Success with [PROXY 1] using user agent Mozilla/5.0 (Windows NT 6.3; WOW64; rv:37.0) Gecko/20100101 Firefox/37.0 on 10. request
Success with [PROXY 2] using user agent Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36 on 11. request
Success with [PROXY 1] using user agent Mozilla/5.0 (Windows NT 6.3; WOW64; rv:37.0) Gecko/20100101 Firefox/37.0 on 12. request
Success with [PROXY 1] using user agent Mozilla/5.0 (Windows NT 6.3; WOW64; rv:37.0) Gecko/20100101 Firefox/37.0 on 13. request
Success with [PROXY 1] using user agent Mozilla/5.0 (Windows NT 6.3; WOW64; rv:37.0) Gecko/20100101 Firefox/37.0 on 14. request
Success with [PROXY 1] using user agent Mozilla/5.0 (Windows NT 6.3; WOW64; rv:37.0) Gecko/20100101 Firefox/37.0 on 15. request
Success with [PROXY 2] using user agent Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36 on 16. request
Success with [PROXY 1] using user agent Mozilla/5.0 (Windows NT 6.3; WOW64; rv:37.0) Gecko/20100101 Firefox/37.0 on 17. request
Success with [PROXY 1] using user agent Mozilla/5.0 (Windows NT 6.3; WOW64; rv:37.0) Gecko/20100101 Firefox/37.0 on 18. request
Success with [PROXY 2] using user agent Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36 on 19. request
[PROXY 1]	 made 10 requests in total
[PROXY 2]	 made 10 requests in total
 */