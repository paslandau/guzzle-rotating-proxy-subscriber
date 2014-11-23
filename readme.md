#GuzzleRotatingProxySubscriber
[![Build Status](https://travis-ci.org/paslandau/GuzzleRotatingProxySubscriber.svg?branch=master)](https://travis-ci.org/paslandau/GuzzleRotatingProxySubscriber)

Plugin for [Guzzle 5](https://github.com/scripts/guzzle) to automatically choose a random element from a set of proxies on each request.

##Description

This plugin takes a set of proxies and uses them randomly on every request, which might come in handy if you need to avoid getting
IP-blocked due to (too) strict limitations.

###Key features

- switches proxies randomly on each request
- each proxy can get a random timeout after each request
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

See `demo*.php` files.

##Requirements

- PHP >= 5.5
- Guzzle >= 5.0.3

##Installation

The recommended way to install GuzzleRotatingProxySubscriber is through [Composer](http://getcomposer.org/).

    curl -sS https://getcomposer.org/installer | php

Next, update your project's composer.json file to include GuzzleRotatingProxySubscriber:

    {
        "repositories": [
            {
                "type": "git",
                "url": "https://github.com/paslandau/GuzzleRotatingProxySubscriber.git"
            }
        ],
        "require": {
             "paslandau/GuzzleRotatingProxySubscriber": "~0"
        }
    }

After installing, you need to require Composer's autoloader:
```php

require 'vendor/autoload.php';
```

##General workflow and customization options
The GuzzleRotatingProxySubscriber uses the `RotatingProxy` class to represent a single proxy. A set of proxies is managed by a `ProxyRotator`, that takes care
of the rotation on every request by hooking into the [before](http://guzzle.readthedocs.org/en/latest/events.html#before) event and changing the 
['proxy' request option](http://guzzle.readthedocs.org/en/latest/clients.html#proxy) of a request.

The response of the request is evaluated either in the [complete](http://guzzle.readthedocs.org/en/latest/events.html#complete) event 
or in the [error](http://guzzle.readthedocs.org/en/latest/events.html#error) event of the guzzle event lifecycle. The evaluation is done by
using a closure that might be defined for each `RotatingProxy` individually. The closure gets the corresponding event (`CompleteEvent` or `ErrorEvent`)
and needs to return either `true` or `false` in order to decide wether the request was successful or not.

An unsucessful request will increase the number of failed requests for a proxy. A distinction is made between the _total number of failed requests_ 
and the _number of requests that failed consecutively_, because you usually want to mark a proxy as "unusable" after it failed like 5 times in a row.
The _number of requests that failed consecutively_ is reset to zero after each successful request.

You might define a random timeout that the proxy must wait after each request before it can be used again.

If all provided proxies become unsuable, you might either choose to continue without using any proxies (= making direct requests) or to let the process
terminate by throwing a `NoProxiesLeftException` before all requests are made.

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
            // nothing went wrong, the request was successfull
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
is chosen that must pass. This time changes after each request, so the first waiting time might be 2 seconds, the second on might be 5 seconds, etc.
The `ProxyRotator` will try to find another proxy that does not have a timeout restriction. If none can be found,
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

##Frequently searched questions

- How can I randomly choose a proxy for each request in Guzzle?
- How can I avoid getting IP blocked?