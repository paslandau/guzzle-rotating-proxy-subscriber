<?php

namespace paslandau\GuzzleRotatingProxySubscriber\Proxy;


use GuzzleHttp\Event\AbstractTransferEvent;
use GuzzleHttp\Event\CompleteEvent;
use GuzzleHttp\Event\EndEvent;
use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Message\RequestInterface;
use paslandau\GuzzleRotatingProxySubscriber\Interval\NullTimeInterval;
use paslandau\GuzzleRotatingProxySubscriber\Interval\TimeIntervalInterface;

class RotatingProxy implements RotatingProxyInterface
{
    /**
     * @var string
     */
    private $proxyString;

    /**
     * @var int
     */
    private $currentTotalFails;

    /**
     * @var int
     */
    private $maxTotalFails;

    /**
     * @var int
     */
    private $currentConsecutiveFails;

    /**
     * @var int
     */
    private $maxConsecutiveFails;

    /**
     * @var bool
     */
    private $blocked;

    /**
     * @var callable
     */
    private $evaluationFunction;

    /**
     * @var TimeIntervalInterface
     */
    private $waitInterval;

    /**
     * @var int
     */
    private $totalRequests;

    /**
     * @param string $proxyString
     * @param callable $evaluationFunction. [optional]. Default: null. (Default: true if $event is an instance of CompleteEvent)
     * @param int $maxConsecutiveFails [optional]. Default: 5. (use -1 for infinite fails)
     * @param int $maxTotalFails [optional]. Default: -1. (use -1 for infinite fails)
     * @param TimeIntervalInterface $randomWaitInterval. Default: null.
     */
    public function __construct($proxyString, callable $evaluationFunction = null, $maxConsecutiveFails = null, $maxTotalFails = null, TimeIntervalInterface $randomWaitInterval = null)
    {
        $this->proxyString = $proxyString;
        if($evaluationFunction === null){
            $evaluationFunction = function(RotatingProxyInterface $proxy, AbstractTransferEvent $event){
                return $event instanceof CompleteEvent;
            };
        }
        $this->evaluationFunction = $evaluationFunction;

        if($maxConsecutiveFails === null){
            $maxConsecutiveFails = 5;
        }
        $this->maxConsecutiveFails = $maxConsecutiveFails;
        $this->currentConsecutiveFails = 0;
        if($maxTotalFails === null){
            $maxTotalFails = -1;
        }
        $this->maxTotalFails = $maxTotalFails;
        $this->currentTotalFails = 0;

        if($randomWaitInterval === null){
            $randomWaitInterval = new NullTimeInterval();
        }
        $this->waitInterval = $randomWaitInterval;

        $this->totalRequests = 0;
        $this->blocked = false;
    }

    /**
     * @param AbstractTransferEvent $event
     */
    public function evaluate(AbstractTransferEvent $event){
        $f = $this->evaluationFunction;
        return $f($this, $event);
    }

    /**
     * @return bool
     */
    public function hasToWait(){
        $res = $this->waitInterval->isReady();
        return ! $res;
    }

    /**
     * @return int
     */
    public function getWaitingTime(){
        return $this->waitInterval->getWaitingTime();
    }

    /**
     *
     */
    public function restartWaitingTime(){
        $this->waitInterval->restart();
    }

    /**
     * Sets the waiting time to 0
     */
    public function skipWaitingTime()
    {
        $this->waitInterval->reset();
    }

    /**
     * @return bool
     */
    public function isUsable(){
        return ( ! $this->isBlocked() && ! $this->hasTooManyFails());
    }

    /**
     * Call after any request
     * @return void
     */
    public function requested(){
        $this->totalRequests++;
    }

    /**
     * Call after a request failed
     * @return void
     */
    public function failed(){
        $this->currentTotalFails++;
        $this->currentConsecutiveFails++;
    }

    /**
     * Call afer a request was successful
     * @return void
     */
    public function succeeded(){
        $this->currentConsecutiveFails = 0;
    }

    /**
     * @return bool
     */
    public function hasTooManyFails(){
        return ($this->hasTooManyConsecutiveFails() || $this->hasTooManyTotalFails());
    }

    /**
     * @return bool
     */
    public function hasTooManyConsecutiveFails(){
        return $this->maxConsecutiveFails > -1 && $this->currentConsecutiveFails >= $this->maxConsecutiveFails;
    }

    /**
     * @return bool
     */
    public function hasTooManyTotalFails(){
        return $this->maxTotalFails > -1 && $this->currentTotalFails >= $this->maxTotalFails;
    }

    /**
     * @return callable
     */
    public function getEvaluationFunction()
    {
        return $this->evaluationFunction;
    }

    /**
     * @param callable $evaluationFunction
     */
    public function setEvaluationFunction(callable $evaluationFunction)
    {
        $this->evaluationFunction = $evaluationFunction;
    }

    /**
     * @return boolean
     */
    public function isBlocked()
    {
        return $this->blocked;
    }

    /**
     */
    public function block()
    {
        $this->blocked = true;
    }

    /**
     */
    public function unblock()
    {
        $this->blocked = false;
    }

    /**
     * @return mixed
     */
    public function getCurrentConsecutiveFails()
    {
        return $this->currentConsecutiveFails;
    }

    /**
     * @param mixed $currentConsecutiveFails
     */
    public function setCurrentConsecutiveFails($currentConsecutiveFails)
    {
        $this->currentConsecutiveFails = $currentConsecutiveFails;
    }

    /**
     * @return mixed
     */
    public function getCurrentTotalFails()
    {
        return $this->currentTotalFails;
    }

    /**
     * @param mixed $currentTotalFails
     */
    public function setCurrentTotalFails($currentTotalFails)
    {
        $this->currentTotalFails = $currentTotalFails;
    }

    /**
     * @return int|null
     */
    public function getMaxConsecutiveFails()
    {
        return $this->maxConsecutiveFails;
    }

    /**
     * @param int|null $maxConsecutiveFails
     */
    public function setMaxConsecutiveFails($maxConsecutiveFails)
    {
        $this->maxConsecutiveFails = $maxConsecutiveFails;
    }

    /**
     * @return int|null
     */
    public function getMaxTotalFails()
    {
        return $this->maxTotalFails;
    }

    /**
     * @param int|null $maxTotalFails
     */
    public function setMaxTotalFails($maxTotalFails)
    {
        $this->maxTotalFails = $maxTotalFails;
    }

    /**
     * @return string
     */
    public function getProxyString()
    {
        return $this->proxyString;
    }

    /**
     * @param string $proxyString
     */
    public function setProxyString($proxyString)
    {
        $this->proxyString = $proxyString;
    }

    /**
     * @return int
     */
    public function getTotalRequests()
    {
        return $this->totalRequests;
    }

    /**
     * @param int $totalRequests
     */
    public function setTotalRequests($totalRequests)
    {
        $this->totalRequests = $totalRequests;
    }

    /**
     * @return TimeIntervalInterface
     */
    public function getWaitInterval()
    {
        return $this->waitInterval;
    }

    /**
     * @param TimeIntervalInterface $waitInterval
     */
    public function setWaitInterval($waitInterval)
    {
        $this->waitInterval = $waitInterval;
    }

    /**
     * @param RequestInterface $request
     * @return RequestInterface
     */
    public function setupRequest(RequestInterface $request){
        $request->getConfig()->set("proxy", $this->getProxyString());
        return $request;
    }
}