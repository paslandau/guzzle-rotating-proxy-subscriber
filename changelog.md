#todo

- explain `generateIdentitiesForProxies` method in readme
- doc comments

#dev-master

###0.1.2

- proxy requests are now evaluated with their own event priority (see `RotatingProxySubscriber::PROXY_*` constants) in the 'complete' and 'error' events
- added `RotatingProxyInterface::GUZZLE_CONFIG_*` constants for proxy evaluation
- provided property to let redirect-requests be performed by the same proxy, see `ProxyRotator::setReuseProxyOnRedirect()` - this is default behavior now
- added `RotatingIdentityProxy` (with tests)
- updated `Build` to include `RotatingIdentityProxy`
- added `Identity` class which holds Cookies, headers values, the last referer and a user agent for now, so that proxy seem more "real"
- added test for cached responses (requests that are intercepted in the before event)
- moved demos to example folder and added mocks to make the demos executable even without real proxies

###0.1.1

- updated repositories to local satis installation

##0.1.0

- changed package name from GuzzleRotatingProxySubscriber to guzzle-rotating-proxy-subscriber

###0.0.2

- fixed changelog
- made `RotationProxySubscriberException` inherit from Guzzles `RequestException` to make it usable by the `MockSubscriber`

###0.0.1

- initial commit