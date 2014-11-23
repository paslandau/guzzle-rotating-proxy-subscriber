<?php namespace paslandau\GuzzleRotatingProxySubscriber;

use GuzzleHttp\Event\AbstractTransferEvent;
use GuzzleHttp\Event\HasEmitterInterface;
use GuzzleHttp\Message\RequestInterface;

interface ProxyRotatorInterface extends HasEmitterInterface
{
    /**
     * @param RequestInterface $request
     * @return bool - returns false if no proxy could be used (no working proxies left but $this->useOwnIp is true), otherwise true.
     */
    public function setupRequest(RequestInterface $request);

    /**
     * @param AbstractTransferEvent $event
     * @return void
     */
    public function evaluateResult(AbstractTransferEvent $event);
}