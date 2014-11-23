<?php

namespace paslandau\GuzzleRotatingProxySubscriber\Builder;


use paslandau\GuzzleRotatingProxySubscriber\Proxy\RotatingProxy;
use paslandau\GuzzleRotatingProxySubscriber\ProxyRotator;
use paslandau\GuzzleRotatingProxySubscriber\Time\RandomTimeInterval;

class Build implements ProxyRotatorBuildOrderInterface{

    /**
     * @var string[]
     */
    private $stringProxies;

    /**
     * @var callable
     */
    private $evaluationFunction = null;

    /**
     * @var bool
     */
    private $useOwnIp = null;

    /**
     * @var int
     */
    private $minimumWorkingProxies = null;

    /**
     * @var int
     */
    private $maxConsecutiveFails = null;

    /**
     * @var int
     */
    private $maxTotalFails = null;

    /**
     * @var int
     */
    private $from;

    /**
     * @var int
     */
    private $to;

    private function __construct(){
    }

    /**
     * @return ProxyRotatorBuildOrderInterface_UseOwnIp
     */
    public static function rotator(){
        return new self();
    }

    /**
     * Expects an array of proxy strings as input, e.g.
     * ["217.0.0.8:8080", "foo@bar:125.12.2.1:7777", "28.3.6.1"]
     * Each proxy string is used to create a new RotatingProxy
     * @param string[] $stringProxies
     * @return \paslandau\GuzzleRotatingProxySubscriber\Builder\Build
     */
    public function withProxiesFromStringArray(array $stringProxies){
        $this->stringProxies = $stringProxies;
        return $this;
    }

    /**
     * Expects a seperated string of proxies as input, e.g.
     * "217.0.0.8:8080, foo@bar:125.12.2.1:7777, 28.3.6.1"
     * The seperator can be defined by the $seperator argument, it defaults to "\n".
     * the string is split on the $seperator and each element is trimmed to get the plain proxy string.
     * @param string $proxyString
     * @param string $seperator [optional]. Default: "\n";
     * @return \paslandau\GuzzleRotatingProxySubscriber\Builder\Build
     */
    public function withProxiesFromString($proxyString, $seperator = null){
        if($seperator === null){
            $seperator = "\n";
        }
        $ps = mb_split($seperator, $proxyString);
        $proxies = [];
        foreach($ps as $p){
            $proxy = trim($p);
            if($proxy != ""){
                $proxies[] = $proxy;
            }
        }
        return $this->withProxiesFromStringArray($proxies);
    }

    public function evaluatesProxyResultsBy(callable $evaluationFunction){
        $this->evaluationFunction = $evaluationFunction;
        return $this;
    }

    public function evaluatesProxyResultsByDefault(){
        $this->evaluationFunction = null;
        return $this;
    }

    public function eachProxyMayFailInTotal($maxTotalFails){
        $this->maxTotalFails = $maxTotalFails;
        return $this;
    }

    public function eachProxyMayFailInfinitlyInTotal(){
        $this->maxTotalFails = -1;
        return $this;
    }

    public function eachProxyMayFailConsecutively($maxConsecutiveFails){
        $this->maxConsecutiveFails = $maxConsecutiveFails;
        return $this;
    }

    public function eachProxyMayFailInfinitlyConsecutively(){
        $this->maxConsecutiveFails = -1;
        return $this;
    }

    public function eachProxyNeedsToWaitSecondsBetweenRequests($from, $to){
        $this->from = $from;
        $this->to = $to;
        return $this;
    }

    public function proxiesDontNeedToWait(){
        $this->from = null;
        $this->to = null;
        return $this;
    }

    public function failsIfNoProxiesAreLeft(){
        $this->useOwnIp = false;
        return $this;
    }

    public function usesOwnIpIfNoProxiesAreLeft(){
        $this->useOwnIp = true;
        return $this;
    }

    public function build(){
        $proxies = [];
        foreach($this->stringProxies as $proxyString){
            $time = null;
            if($this->from !== null && $this->to !== null){
                $time = new RandomTimeInterval($this->from, $this->to);
            }
            $proxies[$proxyString] = new RotatingProxy($proxyString,$this->evaluationFunction,$this->maxConsecutiveFails,$this->maxTotalFails, $time);
        }
        $rotator = new ProxyRotator($proxies, $this->useOwnIp);
        return $rotator;
    }
}