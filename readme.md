#guzzle-rotating-proxy-subscriber
[![Build Status](https://travis-ci.org/paslandau/guzzle-rotating-proxy-subscriber.svg?branch=master)](https://travis-ci.org/paslandau/guzzle-rotating-proxy-subscriber)

Plugin for [Guzzle 5](https://github.com/scripts/guzzle) to automatically choose a random element from a set of proxies on each request.

##Description

This plugin takes a set of proxies and uses them randomly on every request, which might come in handy if you need to avoid getting
IP-blocked due to (too) strict limitations.

###Key features

- switches proxies randomly on each request
- each proxy can get a random timeout after each request
- each proxy can have a list of attached "identities" (an entity including cookies, a user agent and default request headers)
- a request can be evaluated via user-defined closure
- builder class for easy usage
- unit tests

###Basic Usage
```php

// define proxies
$proxy1 = new RotatingProxy("username:password@111.111.111.111:4711");
$proxy2 = new RotatingProxy("username:password@112.112.112.112:4711");

// setup and attach subscriber
$rotator = new ProxyRotator([$proxy1,$proxy2]);
$sub = new RotatingProxySubscriber($rotator);
$client = new Client();
$client->getEmitter()->attach($sub);

// perform the requests
$num = 10;
$url = "http://www.myseosolution.de/scripts/myip.php";
for ($i = 0; $i < $num; $i++) {
    $request =  $client->createRequest("GET",$url);
    try {
        $response = $client->send($request);
        echo "Success with " . $request->getConfig()->get("proxy") . " on $i. request\n";
    } catch (Exception $e) {
        echo "Failed with " . $request->getConfig()->get("proxy") . " on $i. request: " . $e->getMessage() . "\n";
    }
}
```

###Examples

See `examples/demo*.php` files.

##Requirements

- PHP >= 5.5
- Guzzle >= 5.0.3

##Installation

The recommended way to install guzzle-rotating-proxy-subscriber is through [Composer](http://getcomposer.org/).

    curl -sS https://getcomposer.org/installer | php

Next, update your project's composer.json file to include GuzzleRotatingProxySubscriber:

    {
        "repositories": [ { "type": "composer", "url": "http://packages.myseosolution.de/"} ],
        "minimum-stability": "dev",
        "require": {
             "paslandau/guzzle-rotating-proxy-subscriber": "dev-master"
        }
    }

After installing, you need to require Composer's autoloader:
```php

require 'vendor/autoload.php';
```

##General workflow and customization options
The guzzle-rotating-proxy-subscriber uses the `RotatingProxy` class to represent a single proxy. A set of proxies is managed by a `ProxyRotator`, that takes care
of the rotation on every request by hooking into the [before](http://guzzle.readthedocs.org/en/latest/events.html#before) event and changing the 
['proxy' request option](http://guzzle.readthedocs.org/en/latest/clients.html#proxy) of a request. You might choose to further customize the request by 
adding a specific user agent, a cookie session or a some other request headers. In that case you'll need to use the `RotatingIdentityProxy` class. 

The response of the request is evaluated either in the [complete](http://guzzle.readthedocs.org/en/latest/events.html#complete) event 
or in the [error](http://guzzle.readthedocs.org/en/latest/events.html#error) event of the guzzle event lifecycle. The evaluation is done by
using a closure that might be defined for each `RotatingProxy` individually. The closure gets the corresponding event (`CompleteEvent` or `ErrorEvent`)
and needs to return either `true` or `false` in order to decide wether the request was successful or not.

An unsucessful request will increase the number of failed requests for a proxy. A distinction is made between the _total number of failed requests_ 
and the _number of requests that failed consecutively_, because you usually want to mark a proxy as "unusable" after it failed like 5 times in a row.
The _number of requests that failed consecutively_ is reset to zero after each successful request.

You might define a random timeout that the proxy must wait after each request before it can be used again.

If all provided proxies become unsuable, you might either choose to continue without using any proxies (= making direct requests, thus revealing your own IP) or to let the process
terminate by throwing a `NoProxiesLeftException` instead of making the remaining requests.

###Mark a proxy as blocked
A system might block a proxy / IP due to a too aggressive request behaviour. Depending on the system, you might receive a corresponding reponse,
e.g. a certain status code ([Twitter uses 429](https://dev.twitter.com/rest/public/rate-limiting)) or 
maybe just a text message saying something like "Sorry, you're blocked".

In that case, you don't want to use the proxy in question any longer and should call its `block()` method. See next section for an example.

###Use a custom evaluation function for requests

```php

$evaluation = function(RotatingProxyInterface $proxy, AbstractTransferEvent $event){
    if($event instanceof CompleteEvent){
        $content = $event->getResponse()->getBody();
        // example of a custom message returned by a target system
        // for a blocked IP
        $pattern = "#Sorry! You made too many requests, your IP is blocked#";
        if(preg_match($pattern,$content)){
            // The current proxy seems to be blocked
            // so let's mark it as blocked
            $proxy->block();
            return false;
        }else{
            // nothing went wrong, the request was successful
            return true;
        }
    }else{
        // We didn't get a CompleteEvent maybe
        // due to some connection issues at the proxy
        // so let's mark the request as failed
        return false;
    }
};

$proxy = new RotatingProxy("username:password@111.111.111.111:4711", $evaluation);
// or
$proxy->setEvaluationFunction($evaluation);
```

Since the "evaluation" is usually very domain-specific, chances are high that you have something already in place to determine success/failure/blocked states in your application.
In that case you sohuldn't duplicate that code/method but instead use the `GUZZLE_CONFIG_*` constants defined in the `RotatingProxyInterface` to store the result of
that method in the config of the guzzle request and just evaluate that config value. See the following example for clarification:

```php

// function specific to your domain model that performs the evaluation
function domain_specific_evaluation(AbstractTransferEvent $event){
    if($event instanceof CompleteEvent){
        $content = $event->getResponse()->getBody();
        // example of a custom message returned by a target system
        // for a blocked IP
        $pattern = "#Sorry! You made too many requests, your IP is blocked#";
        if(preg_match($pattern,$content)){
            // The current proxy seems to be blocked
            // so let's mark it as blocked
            $event->getRequest()->getConfig()->set(RotatingProxyInterface::GUZZLE_CONFIG_KEY_REQUEST_RESULT, RotatingProxyInterface::GUZZLE_CONFIG_VALUE_REQUEST_RESULT_BLOCKED);
            return false;
        }else{
            // nothing went wrong, the request was successful
            $event->getRequest()->getConfig()->set(RotatingProxyInterface::GUZZLE_CONFIG_KEY_REQUEST_RESULT, RotatingProxyInterface::GUZZLE_CONFIG_VALUE_REQUEST_RESULT_SUCCESS);
            return true;
        }
    }else{
        // We didn't get a CompleteEvent maybe
        // due to some connection issues at the proxy
        // so let's mark the request as failed
        $event->getRequest()->getConfig()->set(RotatingProxyInterface::GUZZLE_CONFIG_KEY_REQUEST_RESULT, RotatingProxyInterface::GUZZLE_CONFIG_VALUE_REQUEST_RESULT_FAILURE);
        return false;
    }
}

$evaluation = function(RotatingProxyInterface $proxy, AbstractTransferEvent $event){
    $result = $event->getRequest()->getConfig()->get(RotatingProxyInterface::GUZZLE_CONFIG_KEY_REQUEST_RESULT);
    switch($result){
        case RotatingProxyInterface::GUZZLE_CONFIG_VALUE_REQUEST_RESULT_SUCCESS:{
            return true;
        }
        case RotatingProxyInterface::GUZZLE_CONFIG_VALUE_REQUEST_RESULT_FAILURE:{
            return false;
        }
        case RotatingProxyInterface::GUZZLE_CONFIG_VALUE_REQUEST_RESULT_BLOCKED:{
            $proxy->block();
            return false;
        }
        default: throw new RuntimeException("Unknown value '{$result}' for config key ".RotatingProxyInterface::GUZZLE_CONFIG_KEY_REQUEST_RESULT);
    }
};

$proxy = new RotatingProxy("username:password@111.111.111.111:4711", $evaluation);
// or
$proxy->setEvaluationFunction($evaluation);
```

###Set a maximum number of fails (total/consecutive)

```php

$maximumFails = 100;
$consecutiveFails = 5;

$proxy = new RotatingProxy("username:password@111.111.111.111:4711", null,$consecutiveFails,$maximumFails);
// or
$proxy->setMaxTotalFails($maximumFails);
$proxy->setMaxConsecutiveFails($consecutiveFails);
```

###Set a random timeout for each proxy before reuse

```php

$from = 1;
$to = 5;
$wait = new RandomTimeInterval($from,$to);

$proxy = new RotatingProxy("username:password@111.111.111.111:4711", null,null,null,$wait);
// or
$proxy->setWaitInterval($wait);
```

The first request using this proxy will be made without delay. Before the second request can be made with this proxy, a random time between 1 and 5 seconds 
is chosen that must pass. This time changes after each request, so the first waiting time might be 2 seconds, the second one might be 5 seconds, etc.
The `ProxyRotator` will try to find another proxy that does not have a time restriction. If none can be found,
a `WaitingEvent` is emitted that contains the proxy with the lowest timeout. You might choose to either skip the waiting time or to let the process sleep until
the waiting time is over and a proxy will be available:

```php

$rotator = new ProxyRotator($proxies);

$waitFn = function (WaitingEvent $event){
    $proxy = $event->getProxy();
    echo "All proxies have a timeout restriction, the lowest is {$proxy->getWaitingTime()}s!\n";
    // nah, we don't wanna wait
    $event->skipWaiting();
};

$rotator->getEmitter()->on(ProxyRotator::EVENT_ON_WAIT, $getWaitingTime);
```

###Define if the requests should be stopped if all proxies are unusable

```php

$proxies = [/* ... */];
$useOwnIp = true;
$rotator = new ProxyRotator($proxies,$useOwnIp);
// or
$rotator->setUseOwnIp($useOwnIp);
```

If set to true, the `ProxyRotator` will _not_ throw an `NoProxiesLeftException` if all proxies are unusable but instead make the remaining 
requests without using any proxies. In that case, a `UseOwnIpEvent` is emitted every time before a request takes place:

```php

$infoFn = function (UseOwnIpEvent $event){
    echo "No proxies are left, making a direct request!\n";
};

$rotator->getEmitter()->on(ProxyRotator::EVENT_ON_USE_OWN_IP,$infoFn);
```

###Use the builder class
The majority of the time it is not necessary to set individual options for every proxy, because you're usually sending requests to the same system
(maybe even the same URL), so the evaluation function should be the same for every `RotatingProxy`, for instance. In that case, the `Build` class might come 
in handy, as it guides you through the process by using a fluent interface in combination with a 
[variant](http://blog.crisp.se/2013/10/09/perlundholm/another-builder-pattern-for-java) of the builder pattern.

```php

$s = "
username:password@111.111.111.111:4711
username:password@112.112.112.112:4711
username:password@113.113.113.113:4711
";

$rotator = Build::rotator()
    ->failsIfNoProxiesAreLeft()                         // throw exception if no proxies are left
    ->withProxiesFromString($s, "\n")                   // build proxies from a string of proxies
                                                        // where each proxy is seperated by a new line
    ->evaluatesProxyResultsByDefault()                  // use the default evaluation function
    ->eachProxyMayFailInfinitlyInTotal()                // don't care about total number of fails for a proxy
    ->eachProxyMayFailConsecutively(5)                  // but block a proxy if it fails 5 times in a row
    ->eachProxyNeedsToWaitSecondsBetweenRequests(1, 3)  // and let it wait between 1 and 3 seconds before making another request
    ->build();
```

This would be equivalent to:

```php

$s = "
username:password@111.111.111.111:4711
username:password@112.112.112.112:4711
username:password@113.113.113.113:4711
";

$lines = explode("\n",$s);
$proxies = [];
foreach($lines as $line){
    $trimmed = trim($line);
    if($trimmed != ""){
        $wait = new RandomTimeInterval(1,3);
        $proxies[$trimmed] = new RotatingProxy($trimmed,null,5,-1,$wait);
    }
}
$rotator = new ProxyRotator($proxies,false);
```

###Use different "identities" to add customization to the requests
There are more advanced systems that do not only check the IP address but take also other "patterns" into account when identifying unusual request behaviour 
(that usually ends in blocking that "pattern"). To prevent being caught by such a system, the `RotatingIdentityProxy` was introduced. Think of it as a
`RotatingProxy` with some customizations flavour to diversify your request footprint.

The customization options are handled via the `Identity` class and - for now - include:
- user agent
- default request headers
- cookie session
- use of the "referer" header

```php
$userAgent = "Mozilla/5.0 (Windows NT 6.3; WOW64; rv:37.0) Gecko/20100101 Firefox/37.0"; // common user agent string for firefox
$defaultRequestHeaders = ["Accept-Language" => "de,en"]; // add a preferred language to each of our requests
$cookieSession = new CookieJar(); // enable cookies for this identity

$identity = new Identity($userAgent,$defaultRequestHeaders,$cookieSession);
$identities = [$identity];
$proxy1 = new RotatingIdentityProxy($identities, "[PROXY 1]");
```

*Note:* Since `RotatingIdentityProxy` inherits from `RotatingProxy` it has the same capabilities in terms of random waiting times.

####Randomly rotate through multiple identities
The `RotatingIdentityProxy` expects not only one identity but and array of identities. You can further provide a `RandomCounterInterval` the will randomly
switch the identity after a certain amount of requests. From the outside (= the server receiving the requests) this looks like a genuine network of different
People sharing the same IP address.

```php

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
```

####Use builder with identities
There are two options that can be used via the builder interface:

- `distributeIdentitiesAmongProxies($identities)`
- `eachProxySwitchesIdentityAfterRequests($min,$max)`

```php

$s = "
username:password@111.111.111.111:4711
username:password@112.112.112.112:4711
username:password@113.113.113.113:4711
";

$identities = [
    new Identity(/*...*/),
    new Identity(/*...*/),
    new Identity(/*...*/),
    new Identity(/*...*/),
    new Identity(/*...*/),
    /*..*/
];

$rotator = Build::rotator()
    ->failsIfNoProxiesAreLeft()                         // throw exception if no proxies are left
    ->withProxiesFromString($s, "\n")                   // build proxies from a string of proxies
                                                        // where each proxy is seperated by a new line
    ->evaluatesProxyResultsByDefault()                  // use the default evaluation function
    ->eachProxyMayFailInfinitlyInTotal()                // don't care about total number of fails for a proxy
    ->eachProxyMayFailConsecutively(5)                  // but block a proxy if it fails 5 times in a row
    ->eachProxyNeedsToWaitSecondsBetweenRequests(1, 3)  // and let it wait between 1 and 3 seconds before making another request
    // identity options
    ->distributeIdentitiesAmongProxies($identities)     // setup each proxy with a subset of $identities - no identity is assigne twice!       
    ->eachProxySwitchesIdentityAfterRequests(3,7)       // switch to another identity after between 3 and 7 requests
    ->build();
```

##Frequently searched questions

- How can I randomly choose a proxy for each request in Guzzle?
- How can I avoid getting IP blocked?