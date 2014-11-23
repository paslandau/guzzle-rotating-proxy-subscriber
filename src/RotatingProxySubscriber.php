<?php
namespace paslandau\GuzzleRotatingProxySubscriber;
use GuzzleHttp\Event\AbstractTransferEvent;
use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Event\EndEvent;
use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Event\SubscriberInterface;
use GuzzleHttp\Pool;
use GuzzleHttp\Stream\Stream;
use paslandau\GuzzleRotatingProxySubscriber\Exceptions\RotatingProxySubscriberException;
use paslandau\Utility\ExceptionUtil;

/**
 * Created by PhpStorm.
 * User: Hirnhamster
 * Date: 12.10.2014
 * Time: 14:55
 */

class RotatingProxySubscriber implements SubscriberInterface{

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

    public function setup(BeforeEvent $event){
        $request = $event->getRequest();
        $this->proxyRotator->setupRequest($request);
    }

    public function evaluate(AbstractTransferEvent $event)
    {
        $this->proxyRotator->evaluateResult($event);
    }
}