<?php

namespace paslandau\GuzzleRotatingProxySubscriber\Exceptions;


use GuzzleHttp\Message\RequestInterface;
use paslandau\GuzzleRotatingProxySubscriber\ProxyRotatorInterface;

class NoProxiesLeftException extends RotatingProxySubscriberException{

    /**
     * @var ProxyRotatorInterface
     */
    private $proxyRotator;

    /**
     * @param ProxyRotatorInterface $proxyRotator
     * @param RequestInterface $request
     * @param string $message
     * @param null|\Exception $previous
     */
    public function __construct(ProxyRotatorInterface $proxyRotator, RequestInterface $request, $message, \Exception $previous = null){
        $this->proxyRotator = $proxyRotator;

        parent::__construct($message, $request, null, $previous);
    }

    /**
     * @return ProxyRotatorInterface
     */
    public function getProxyRotator()
    {
        return $this->proxyRotator;
    }

    /**
     * @param ProxyRotatorInterface $proxyRotator
     */
    public function setProxyRotator($proxyRotator)
    {
        $this->proxyRotator = $proxyRotator;
    }


}