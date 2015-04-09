<?php
namespace paslandau\GuzzleRotatingProxySubscriber;

use GuzzleHttp\Event\AbstractTransferEvent;
use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Event\RequestEvents;
use GuzzleHttp\Event\SubscriberInterface;

define("EARLIER_THAN_GUZZLE_REQUEST_EVENTS_REDIRECT_RESPONSE", (RequestEvents::REDIRECT_RESPONSE + 10));

class RotatingProxySubscriber implements SubscriberInterface
{

    /**
     * Event priority for the evaluation of a response.
     * This should be done pretty late so that any domain-logic evaluations could already take place.
     */
    const PROXY_COMPLETE_EVENT = -500;
    /**
     * Event priority for the preparation of a request.
     * This should be done pretty early.
     */
    const PROXY_PREPARE_EVENT = 100;
    /**
     * @var ProxyRotatorInterface
     */
    private $proxyRotator;

    /**
     * @param ProxyRotatorInterface $proxyRotator
     */
    function __construct(ProxyRotatorInterface $proxyRotator)
    {
        $this->proxyRotator = $proxyRotator;
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * @return array
     */
    public function getEvents()
    {
        return array(
            'before' => ['setup',self::PROXY_PREPARE_EVENT],
//            'end' => ['evaluate'] // Note: We cannot use the end event because it would not be possible to use AbstractTransferEvent::retry()
                                    //(only the last retry would then be used to determine the proxy result
                                    // therefore, we're going to use complete and error
                                    // and we use them slightly before the RedirectSubscriber kicks in, so that we can evaluate the results
                                    // of every requests - even if it's a redirect
            'complete' => ['evaluate',self::PROXY_COMPLETE_EVENT],
            'error' => ['evaluate',self::PROXY_COMPLETE_EVENT]
//            'complete' => ['evaluate',RequestEvents::REDIRECT_RESPONSE +1],
//            'error' => ['evaluate',RequestEvents::REDIRECT_RESPONSE +1]
        );
    }

    public function setup(BeforeEvent $event)
    {
        $request = $event->getRequest();
        $this->proxyRotator->setupRequest($request);
    }

    public function evaluate(AbstractTransferEvent $event)
    {
        $this->proxyRotator->evaluateResult($event);
    }
}