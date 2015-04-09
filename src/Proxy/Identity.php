<?php

namespace paslandau\GuzzleRotatingProxySubscriber\Proxy;


use GuzzleHttp\Cookie\CookieJarInterface;
use GuzzleHttp\Message\RequestInterface;

class Identity implements IdentityInterface
{

    /**
     * @var string
     */
    private $userAgent;
    /**
     * @var string[]
     */
    private $defaultRequestHeaders;
    /**
     * @var CookieJarInterface
     */
    private $cookieJar;

    /**
     * @var string
     */
    private $referer;

    /**
     * @param string|null $userAgent
     * @param string|null $defaultRequestHeaders
     * @param CookieJarInterface|null $cookieJar
     */
    function __construct($userAgent = null, $defaultRequestHeaders = null, CookieJarInterface $cookieJar = null)
    {
        $this->userAgent = $userAgent;
        $this->defaultRequestHeaders = $defaultRequestHeaders;
        $this->cookieJar = $cookieJar;
        $this->referer = null;
    }

    /**
     * @return string
     */
    public function getUserAgent()
    {
        return $this->userAgent;
    }

    /**
     * @param string $userAgent
     */
    public function setUserAgent($userAgent)
    {
        $this->userAgent = $userAgent;
    }

    /**
     * @return \string[]
     */
    public function getDefaultRequestHeaders()
    {
        return $this->defaultRequestHeaders;
    }

    /**
     * @param \string[] $defaultRequestHeaders
     */
    public function setDefaultRequestHeaders($defaultRequestHeaders)
    {
        $this->defaultRequestHeaders = $defaultRequestHeaders;
    }

    /**
     * @return CookieJarInterface
     */
    public function getCookieJar()
    {
        return $this->cookieJar;
    }

    /**
     * @param CookieJarInterface $cookieJar
     */
    public function setCookieJar($cookieJar)
    {
        $this->cookieJar = $cookieJar;
    }

    /**
     * @return string|null
     */
    public function getReferer()
    {
        return $this->referer;
    }

    /**
     * @param string $referer
     */
    public function setReferer($referer)
    {
        $this->referer = $referer;
    }
}