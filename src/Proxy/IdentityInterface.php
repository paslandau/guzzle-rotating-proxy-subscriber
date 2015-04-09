<?php namespace paslandau\GuzzleRotatingProxySubscriber\Proxy;

use GuzzleHttp\Cookie\CookieJarInterface;

interface IdentityInterface
{

    /**
     * @return string[]|null
     */
    public function getDefaultRequestHeaders();

    /**
     * @return string|null
     */
    public function getUserAgent();

    /**
     * @return CookieJarInterface|null
     */
    public function getCookieJar();

    /**
     * @return string|null
     */
    public function getReferer();

    /**
     * @param string $referer
     */
    public function setReferer($referer);

}