<?php

namespace paslandau\GuzzleRotatingProxySubscriber\Exceptions;


use paslandau\GuzzleRotatingProxySubscriber\ProxyRotatorInterface;

class NoProxiesLeftException extends RotatingProxySubscriberException{

    /**
     * @var ProxyRotatorInterface
     */
    private $proxyRotator;

    public function __construct(ProxyRotatorInterface $proxyRotator, $message, $code = null, $previous = null){
        $this->proxyRotator = $proxyRotator;

        if($code === null){
            $code = 0;
        }
        parent::__construct($message, $code, $previous);
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