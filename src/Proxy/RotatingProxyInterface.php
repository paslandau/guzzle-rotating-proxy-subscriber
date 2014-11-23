<?php namespace paslandau\GuzzleRotatingProxySubscriber\Proxy;

use GuzzleHttp\Event\AbstractTransferEvent;

interface RotatingProxyInterface
{
    /**
     * @return bool
     */
    public function isUsable();

    /**
     * Call after a request failed
     * @return void
     */
    public function failed();

    /**
     * Call after any request
     * @return void
     */
    public function requested();

    /**
     * Call afer a request was successful
     * @return void
     */
    public function succeeded();

    /**
     */
    public function block();

    /**
     */
    public function unblock();

    /**
     * @return bool
     */
    public function hasToWait();

    /**
     * @return int
     */
    public function getWaitingTime();


    /**
     * Sets the waiting time to 0
     */
    public function skipWaitingTime();

    /**
     *
     */
    public function restartWaitingTime();

    /**
     * @return string
     */
    public function getProxyString();

    /**
     * @param AbstractTransferEvent $event
     */
    public function evaluate(AbstractTransferEvent $event);
}