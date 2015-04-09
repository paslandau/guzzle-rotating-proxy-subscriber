<?php

namespace paslandau\GuzzleRotatingProxySubscriber\Builder;


use GuzzleHttp\Cookie\CookieJar;
use paslandau\GuzzleRotatingProxySubscriber\Interval\RandomCounterInterval;
use paslandau\GuzzleRotatingProxySubscriber\Proxy\Identity;
use paslandau\GuzzleRotatingProxySubscriber\Proxy\RotatingIdentityProxy;
use paslandau\GuzzleRotatingProxySubscriber\Proxy\RotatingProxy;
use paslandau\GuzzleRotatingProxySubscriber\ProxyRotator;
use paslandau\GuzzleRotatingProxySubscriber\Interval\RandomTimeInterval;

class Build implements ProxyRotatorBuildOrderInterface{

    /**
     * @var string
     */
    private $proxyClass;

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

    /**
     * @var Identity[]
     */
    private $identities;

    /**
     * @var int
     */
    private $fromRequest;

    /**
     * @var int
     */
    private $toRequest;

    private function __construct(){
        $this->proxyClass = RotatingProxy::class;
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
        $class = $this->proxyClass;
        if($this->identities !== null && count($this->identities) < count($this->stringProxies)){
            throw new \InvalidArgumentException("Number of identities ".count($this->identities)." must be greater or equal number of proxies ".count($this->stringProxies));
        }
        $identitySlice = floor(count($this->identities)/count($this->stringProxies));
        $rest = count($this->identities)%count($this->stringProxies);
        foreach($this->stringProxies as $proxyString){
            $time = null;
            if($this->from !== null && $this->to !== null){
                $time = new RandomTimeInterval($this->from, $this->to);
            }
            if($class == RotatingProxy::class) {
                $proxies[$proxyString] = new $class($proxyString, $this->evaluationFunction, $this->maxConsecutiveFails, $this->maxTotalFails, $time);
            }elseif($class == RotatingIdentityProxy::class) {
                $counter = null;
                if($this->fromRequest !== null && $this->toRequest !== null){
                    $counter = new RandomCounterInterval($this->from, $this->to);
                }
                $slice = $identitySlice;
                if($rest > 0){ // if we still got a rest from the division, we can add an additional identity
                    $rest--;
                    $slice++;
                }
                $identities = array_splice($this->identities,0,$slice);
                $proxies[$proxyString] = new $class($identities, $proxyString, null, $counter, $this->evaluationFunction, $this->maxConsecutiveFails, $this->maxTotalFails, $time);
            }
        }
        $rotator = new ProxyRotator($proxies, $this->useOwnIp);
        return $rotator;
    }

    /**
     * @param Identity[] $identities
     * @return ProxyRotatorBuildOrderInterface_SwitchIdentities
     */
    public function distributeIdentitiesAmongProxies(array $identities)
    {
        $this->proxyClass = RotatingIdentityProxy::class;
        $this->identities = $identities;
        return $this;
    }

    /**
     * @param int $nrOfIdentitiesPerProxy
     * @param string[] $userAgentSeed
     * @param string[][] $requestHeaderSeed
     * @return ProxyRotatorBuildOrderInterface_SwitchIdentities
     * @internal param \paslandau\GuzzleRotatingProxySubscriber\Proxy\Identity[] $identities
     */
    public function generateIdentitiesForProxies($nrOfIdentitiesPerProxy, array $userAgentSeed, array $requestHeaderSeed)
    {
        $this->proxyClass = RotatingIdentityProxy::class;
        $proxies = count($this->stringProxies);
        $targetIdentityCount = $nrOfIdentitiesPerProxy*$proxies;
        $identities = [];
        for($i=0; $i < $targetIdentityCount; $i++){
            $uaKey = array_rand($userAgentSeed);
            $ua = $userAgentSeed[$uaKey];
            $headersKey = array_rand($requestHeaderSeed);
            $headers = $requestHeaderSeed[$headersKey];
            $cookieJar = new CookieJar();
            $identities[] = new Identity($ua,$headers,$cookieJar);
        }
        $this->identities = $identities;
        return $this;
    }

    /**
     * @return ProxyRotatorBuildOrderInterface_Build
     */
    public function eachProxySwitchesIdentityAfterEachRequest()
    {
        $this->fromRequest = null;
        $this->toRequest = null;
        return $this;
    }

    /**
     * @param int $from
     * @param int $to
     * @return ProxyRotatorBuildOrderInterface_Build
     */
    public function eachProxySwitchesIdentityAfterRequests($from, $to)
    {
        $this->fromRequest = $from;
        $this->toRequest = $to;
        return $this;
    }

}