<?php
namespace paslandau\GuzzleRotatingProxySubscriber;

use GuzzleHttp\Event\AbstractTransferEvent;
use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Event\SubscriberInterface;

class RotatingProxySubscriber implements SubscriberInterface
{

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
            'before' => ['setup'],
            'complete' => ['evaluate'],
            'error' => ['evaluate']
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