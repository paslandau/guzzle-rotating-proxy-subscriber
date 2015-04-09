<?php namespace paslandau\GuzzleRotatingProxySubscriber\Proxy;

use GuzzleHttp\Event\AbstractTransferEvent;
use GuzzleHttp\Message\RequestInterface;

interface RotatingProxyInterface
{
    /**
     * Use these constants to define in the config of a guzzle request if that request has been successful.
     * This simplifies the evaluation since the "complex" evaluation can be done in the corresponding domain so
     * that only the result has to be checke in the evaluat() method of this interface.
     */
    const GUZZLE_CONFIG_KEY_REQUEST_RESULT = "rotating_proxy_subscriber.result";
    const GUZZLE_CONFIG_VALUE_REQUEST_RESULT_SUCCESS = "success";
    const GUZZLE_CONFIG_VALUE_REQUEST_RESULT_FAILURE = "failure";
    const GUZZLE_CONFIG_VALUE_REQUEST_RESULT_BLOCKED = "blocked";
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

    /**
     * @param RequestInterface $request
     * @return RequestInterface
     */
    public function setupRequest(RequestInterface $request);
}