<?php
/**
 * Created by PhpStorm.
 * User: Hirnhamster
 * Date: 04.11.2014
 * Time: 10:52
 */

namespace paslandau\GuzzleRotatingProxySubscriber;


use GuzzleHttp\Event\AbstractTransferEvent;
use GuzzleHttp\Event\EndEvent;
use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Event\HasEmitterTrait;
use GuzzleHttp\Message\RequestInterface;
use paslandau\GuzzleRotatingProxySubscriber\Events\UseOwnIpEvent;
use paslandau\GuzzleRotatingProxySubscriber\Events\WaitingEvent;
use paslandau\GuzzleRotatingProxySubscriber\Exceptions\NoProxiesLeftException;
use paslandau\GuzzleRotatingProxySubscriber\Exceptions\RotatingProxySubscriberException;
use paslandau\GuzzleRotatingProxySubscriber\Proxy\NullProxy;
use paslandau\GuzzleRotatingProxySubscriber\Proxy\RotatingProxyInterface;
use paslandau\GuzzleRotatingProxySubscriber\Random\RandomizerInterface;
use paslandau\GuzzleRotatingProxySubscriber\Random\SystemRandomizer;

class ProxyRotator implements ProxyRotatorInterface
{
    use HasEmitterTrait;

    private static $REQUEST_CONFIG_KEY = "proxy_request_id";
    const EVENT_ON_WAIT = "waiting";
    const EVENT_ON_USE_OWN_IP= "using_own_ip";

    /**
     * @var RotatingProxyInterface[]
     */
    private $proxies;

    /**
     * @var RotatingProxyInterface[]
     */
    private $workingProxies;

    /**
     * @var RotatingProxyInterface[]
     */
    private $requestId2ProxyMap;

    /**
     * @var bool
     */
    private $useOwnIp;

    /**
     * @var RandomizerInterface
     */
    private $randomizer;

    /**
     * @var bool - If true, the same proxy is used for redirect requests
     */
    private $reuseSameProxyOnRedirect;

    /**
     * @param RandomizerInterface $randomizer
     * @param RotatingProxyInterface[] $proxies
     * @param bool $useOwnIp [optional]. Default: false.
     */
    function __construct(array $proxies = null, $useOwnIp = null, RandomizerInterface $randomizer = null)
    {
        if($randomizer === null){
            $randomizer = new SystemRandomizer();
        }
        $this->randomizer = $randomizer;
        if($proxies === null){
            $proxies = [];
        }
        $this->setProxies($proxies);
        if($useOwnIp === null){
            $useOwnIp = false;
        }
        $this->useOwnIp = $useOwnIp;
        $this->requestId2ProxyMap = [];
        $this->reuseSameProxyOnRedirect = true;
    }


    /**
     * @return RotatingProxyInterface[]
     */
    public function getProxies()
    {
        return $this->proxies;
    }

    /**
     * @param RotatingProxyInterface[] $proxies
     */
    public function setProxies(array $proxies)
    {
        foreach($proxies as $proxy) {
            $this->proxies[$proxy->getProxyString()] = $proxy;
            $this->workingProxies[$proxy->getProxyString()] = $proxy;
        }
    }


    /**
     * @return RotatingProxyInterface[]
     */
    public function getWorkingProxies()
    {
        return $this->workingProxies;
    }

    /**
     * @return boolean
     */
    public function isUseOwnIp()
    {
        return $this->useOwnIp;
    }

    /**
     * @param boolean $useOwnIp
     */
    public function setUseOwnIp($useOwnIp)
    {
        $this->useOwnIp = $useOwnIp;
    }

    /**
     * @return boolean
     */
    public function isReuseSameProxyOnRedirect()
    {
        return $this->reuseSameProxyOnRedirect;
    }

    /**
     * @param boolean $reuseSameProxyOnRedirect
     */
    public function setReuseSameProxyOnRedirect($reuseSameProxyOnRedirect)
    {
        $this->reuseSameProxyOnRedirect = $reuseSameProxyOnRedirect;
    }

    /**
     * @param RequestInterface $request
     * @return bool - returns false if no proxy could be used (no working proxies left but $this->useOwnIp is true), otherwise true.
     */
    public function setupRequest(RequestInterface $request){
        if($this->reuseSameProxyOnRedirect && $this->isRedirectRequest($request)){ // do not change proxy on redirect
            return true;
        }
        $proxy = $this->getWorkingProxy($request);
        $this->requestId2ProxyMap[] = $proxy;
        $keys = array_keys($this->requestId2ProxyMap);
        $requestId = end($keys); // get newly inserted key
        $request->getConfig()->set(self::$REQUEST_CONFIG_KEY, $requestId);
        $proxy->restartWaitingTime();
        $request = $proxy->setupRequest($request);
        if(!$proxy instanceof NullProxy){
            return true;
        }
        return false;
    }

    /**
     * Checks wether $request is a redirect by inspecting the "redirect_count" property of the request config
     * used by to define the number of redirects of a request GuzzleHttp\Subscriber\Redirect
     * @param RequestInterface $request
     * @return bool
     */
    private function isRedirectRequest(RequestInterface $request){
        $isRedirect = $request->getConfig()->get("redirect_count");
        return ($isRedirect !== null && $isRedirect > 0);
    }

    /**
     * @param AbstractTransferEvent $event
     * @throws RotatingProxySubscriberException
     * @return void
     */
    public function evaluateResult(AbstractTransferEvent $event){
        $request = $event->getRequest();
        if($event instanceof ErrorEvent) {
            $exception = $event->getException();
            if($exception !== null) {
                do {
                    if ($exception instanceof NoProxiesLeftException) {
                        throw $exception;
                    }
                    $exception = $exception->getPrevious();
                } while ($exception !== null);
            }
        }
        $requestId = $request->getConfig()->get(self::$REQUEST_CONFIG_KEY);
        if($requestId === null){
            return;
            // Question: What about caches? A cached response might be served so that no proxy was used.
            // SOLUTION: simply return without exception.
//            throw new RotatingProxySubscriberException("Config key '".self::$REQUEST_CONFIG_KEY."' not found in request config - this shouldn't happen...");
        }
        if(!array_key_exists($requestId, $this->requestId2ProxyMap)){
            // This method really should only be called once, because it determines the result of the proxy request
            // if it's called multiple times, something is probably wrong. A possible scenario:
            // Client has a RotatingProxySubscriber and an additional function attached to the complete event that checks wether the
            // response was logically correct (e.g. contained the right json format) and throws an exception if not.
            // In that case, this method (evaluateResult) would be called twice: one time in the complete event and the next time
            // after the exception was thrown (which results in an error event being emitted).
            // SOLUTION: This can be solved by giving this RotatingProxySubscriber a lower priority so that is called last
            // Question: Does this introduce problems with ->retry() calls, e.g. is only the last call counted?
            // Answer: No - see RotatingProxySubscriberTest::test_integration_RetryingRequestsShouldIncreaseFailesAccordingly()
            $msg = "Request with id '{$requestId}' not found - it was probably already processed. Make sure not to pass on multiple events for the same request. This might be influenced by the event priority.";
            throw new RotatingProxySubscriberException($msg,$event->getRequest());
        }
        $proxy = $this->requestId2ProxyMap[$requestId];
        unset($this->requestId2ProxyMap[$requestId]);
        $proxy->requested(); // increase request count
        if($proxy->evaluate($event)){
            $proxy->succeeded();
        }else{
            $proxy->failed();
        }
    }

    /**
     * @param RequestInterface $request
     * @return RotatingProxyInterface
     */
    protected function getWorkingProxy(RequestInterface $request){
        $waitingProxies = [];
        $waitingProxyTimes = [];
        while($this->hasEnoughWorkingProxies()){
            $randKey = $this->randomizer->randKey($this->workingProxies);
//            $randKey = array_rand($this->workingProxies);
            $proxy = $this->workingProxies[$randKey];
            if(!$proxy->isUsable()){
                unset($this->workingProxies[$randKey]);
                continue;
            }
            if($proxy->hasToWait()){
                $waitingProxyTimes [$randKey] = $proxy->getWaitingTime();
                $waitingProxies [$randKey] = $proxy;
                unset($this->workingProxies[$randKey]);
                continue;
            }
            $this->workingProxies += $waitingProxies;
            return $proxy;
        }
        if(count($waitingProxies) > 0) {
            asort($waitingProxyTimes);
            reset($waitingProxyTimes);
            $minKey = key($waitingProxies);
            /** @var RotatingProxyInterface $minWaitingProxy */
            $minWaitingProxy = $waitingProxies[$minKey];
//            $minimumWaitingTime = ceil(reset($waitingProxyTimes));
            $event = new WaitingEvent($minWaitingProxy);
            $this->getEmitter()->emit(self::EVENT_ON_WAIT,$event);
            $minimumWaitingTime = $minWaitingProxy->getWaitingTime(); // the WaitingTime might have been changed by a listener to the WaitingEvent
            if($minimumWaitingTime > 0) {
                sleep($minimumWaitingTime);
            }
            $this->workingProxies += $waitingProxies;
            return $this->getWorkingProxy($request);
        }

        if($this->useOwnIp){
            $event = new UseOwnIpEvent();
            $this->getEmitter()->emit(self::EVENT_ON_USE_OWN_IP,$event);
            return new NullProxy();
        }
        $msg = "No proxies left and usage of own IP is forbidden";
        throw new NoProxiesLeftException($this,$request,$msg);
    }

    /**
     * @return bool
     */
    private function hasEnoughWorkingProxies(){
        return count($this->workingProxies) > 0;
    }
}