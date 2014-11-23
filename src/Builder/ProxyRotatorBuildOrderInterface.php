<?php

namespace paslandau\GuzzleRotatingProxySubscriber\Builder;

use paslandau\GuzzleRotatingProxySubscriber\ProxyRotator;
use paslandau\GuzzleRotatingProxySubscriber\ProxyRotatorInterface;

interface ProxyRotatorBuildOrderInterface_UseOwnIp {
    /**
     * @return ProxyRotatorBuildOrderInterface_WithProxies
     */
    public function failsIfNoProxiesAreLeft();

    /**
     * @return ProxyRotatorBuildOrderInterface_WithProxies
     */
    public function usesOwnIpIfNoProxiesAreLeft();
}

interface ProxyRotatorBuildOrderInterface_WithProxies {
    /**
     * Expects an array of proxy strings as input, e.g.
     * ["217.0.0.8:8080", "foo@bar:125.12.2.1:7777", "28.3.6.1"]
     * Each proxy string is used to create a new RotatingProxy
     * @param string[] $stringProxies
     * @return ProxyRotatorBuildOrderInterface_Evaluate
     */
    public function withProxiesFromStringArray(array $stringProxies);

    /**
     * Expects a seperated string of proxies as input, e.g.
     * "217.0.0.8:8080, foo@bar:125.12.2.1:7777, 28.3.6.1"
     * The seperator can be defined by the $seperator argument, it defaults to "\n".
     * the string is split on the $seperator and each element is trimmed to get the plain proxy string.
     * @param string $proxyString
     * @param string $seperator [optional]. Default: "\n";
     * @return ProxyRotatorBuildOrderInterface_Evaluate
     */
    public function withProxiesFromString($proxyString, $seperator = null);
}

interface ProxyRotatorBuildOrderInterface_Evaluate {
    /**
     * @param callable $evaluationFunction
     * @return ProxyRotatorBuildOrderInterface_TotalFails
     */
    public function evaluatesProxyResultsBy(callable $evaluationFunction);

    /**
     * @return ProxyRotatorBuildOrderInterface_TotalFails
     */
    public function evaluatesProxyResultsByDefault();

}

interface ProxyRotatorBuildOrderInterface_TotalFails {
    /**
     * @param int $maxTotalFails
     * @return ProxyRotatorBuildOrderInterface_ConsecutiveFails
     */
    public function eachProxyMayFailInTotal($maxTotalFails);

    /**
     * @return ProxyRotatorBuildOrderInterface_ConsecutiveFails
     */
    public function eachProxyMayFailInfinitlyInTotal();
}

interface ProxyRotatorBuildOrderInterface_ConsecutiveFails {
    /**
     * @param int $maxConsecutiveFails
     * @return ProxyRotatorBuildOrderInterface_Wait
     */
    public function eachProxyMayFailConsecutively($maxConsecutiveFails);

    /**
     * @return ProxyRotatorBuildOrderInterface_Wait
     */
    public function eachProxyMayFailInfinitlyConsecutively();
}

interface ProxyRotatorBuildOrderInterface_Wait {
    /**
     * @param int $from
     * @param int $to
     * @return ProxyRotatorBuildOrderInterface_Build
     */
    public function eachProxyNeedsToWaitSecondsBetweenRequests($from, $to);

    /**
     * @return ProxyRotatorBuildOrderInterface_Build
     */
    public function proxiesDontNeedToWait();
}

interface ProxyRotatorBuildOrderInterface_Build {
    /**
     * @return ProxyRotator
     */
    public function build();
}

interface ProxyRotatorBuildOrderInterface extends ProxyRotatorBuildOrderInterface_UseOwnIp, ProxyRotatorBuildOrderInterface_WithProxies, ProxyRotatorBuildOrderInterface_Evaluate, ProxyRotatorBuildOrderInterface_TotalFails, ProxyRotatorBuildOrderInterface_ConsecutiveFails, ProxyRotatorBuildOrderInterface_Build {

} 