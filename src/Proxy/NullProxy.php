<?php

namespace paslandau\GuzzleRotatingProxySubscriber\Proxy;


use GuzzleHttp\Event\AbstractTransferEvent;
use GuzzleHttp\Message\RequestInterface;

class NullProxy implements RotatingProxyInterface{
    /**
     * @return bool
     */
    public function isUsable()
    {
        return true;
    }

    /**
     * Call after a request failed
     * @return void
     */
    public function failed()
    {
        // Do nothing
    }

    /**
     * Call afer a request was successful
     * @return void
     */
    public function succeeded()
    {
        // Do nothing
    }

    /**
     */
    public function block()
    {
        // Do nothing
    }

    /**
     */
    public function unblock()
    {
        // Do nothing
    }


    /**
     * @return string
     */
    public function getProxyString()
    {
        return "[[NullProxy]]";
    }

    /**
     * @param AbstractTransferEvent $event
     */
    public function evaluate(AbstractTransferEvent $event)
    {
        // Do nothing
    }

    /**
     * Call after any request
     * @return void
     */
    public function requested()
    {
        // Do nothing
    }

    /**
     * @return bool
     */
    public function hasToWait()
    {
        return false;
    }

    /**
     * @return int
     */
    public function getWaitingTime()
    {
        return 0;
    }

    /**
     *
     */
    public function restartWaitingTime()
    {
        // Do nothing
    }

    /**
     *
     */
    public function skipWaitingTime(){
        // Do nothing
    }

    /**
     * @param RequestInterface $request
     * @return RequestInterface
     */
    public function setupRequest(RequestInterface $request){
        return $request;
    }
} 