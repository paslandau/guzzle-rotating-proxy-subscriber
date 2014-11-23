<?php

namespace paslandau\GuzzleRotatingProxySubscriber\Events;

use GuzzleHttp\Event\AbstractEvent;
use paslandau\GuzzleRotatingProxySubscriber\Proxy\RotatingProxyInterface;

class WaitingEvent extends AbstractEvent{
    /**
     * @var RotatingProxyInterface
     */
    private $proxy;

    /**
     * @param RotatingProxyInterface $proxy
     */
    function __construct(RotatingProxyInterface $proxy)
    {
        $this->proxy = $proxy;
    }

    /**
     * @return RotatingProxyInterface
     */
    public function getProxy()
    {
        return $this->proxy;
    }

    /**
     * Skips the waiting time
     */
    public function skipWaiting(){
        $this->proxy->skipWaitingTime();
    }
}