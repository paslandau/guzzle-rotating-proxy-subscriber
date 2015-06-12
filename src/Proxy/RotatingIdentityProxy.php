<?php

namespace paslandau\GuzzleRotatingProxySubscriber\Proxy;


use GuzzleHttp\Event\AbstractTransferEvent;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Subscriber\Cookie;
use paslandau\GuzzleRotatingProxySubscriber\Random\RandomizerInterface;
use paslandau\GuzzleRotatingProxySubscriber\Random\SystemRandomizer;
use paslandau\GuzzleRotatingProxySubscriber\Interval\NullRandomCounter;
use paslandau\GuzzleRotatingProxySubscriber\Interval\RandomCounterIntervalInterface;
use paslandau\GuzzleRotatingProxySubscriber\Interval\TimeIntervalInterface;

class RotatingIdentityProxy extends RotatingProxy
{

    /**
     * @var IdentityInterface[]
     */
    private $identities;
    /**
     * @var IdentityInterface
     */
    private $currentIdentity;

    /**
     * @var RandomizerInterface
     */
    private $randomizer;

    /**
     * @var RandomCounterIntervalInterface
     */
    private $randomCounter;

    /**
     * @param IdentityInterface[] $identities
     * @param string $proxyString
     * @param RandomizerInterface|null $randomizer
     * @param RandomCounterIntervalInterface|null $randomCounter
     * @param callable $evaluationFunction . [optional]. Default: null. (Default: true if $event is an instance of CompleteEvent)
     * @param int $maxConsecutiveFails [optional]. Default: 5. (use -1 for infinite fails)
     * @param int $maxTotalFails [optional]. Default: -1. (use -1 for infinite fails)
     * @param TimeIntervalInterface $randomWaitInterval . Default: null.
     * @throws \InvalidArgumentException
     */
    public function __construct(array $identities, $proxyString, RandomizerInterface $randomizer = null, RandomCounterIntervalInterface $randomCounter = null, callable $evaluationFunction = null, $maxConsecutiveFails = null, $maxTotalFails = null, TimeIntervalInterface $randomWaitInterval = null)
    {
        if (count($identities) == 0) {
            throw new \InvalidArgumentException("Number of identities must be greater 0");
        }
        $this->identities = $identities;
        if ($randomizer === null) {
            $randomizer = new SystemRandomizer();
        }
        $this->randomizer = $randomizer;
        if ($randomCounter === null) {
            $randomCounter = new NullRandomCounter();
        }
        $this->randomCounter = $randomCounter;
        parent::__construct($proxyString, $evaluationFunction, $maxConsecutiveFails, $maxTotalFails, $randomWaitInterval);
    }

    /**
     * Save referer to identity before passing the event on to the evaluation function
     * @param AbstractTransferEvent $event
     */
    public function evaluate(AbstractTransferEvent $event)
    {
        $resp = $event->getResponse();
        if ($resp != null) {
            $url = $resp->getEffectiveUrl();
            if ($url !== "") {
                $identity = $this->getCurrentIdentity();
                $identity->setReferer($url);
            }
        }
        return parent::evaluate($event);
    }

    /**
     * Check if the identity should be switched after each request
     */
    public function requested()
    {
        parent::requested();
        $this->randomCounter->incrementCounter();
        if ($this->randomCounter->isReady()) {
            $this->switchIdentity();
        }
    }

    /**
     * @return IdentityInterface[]
     */
    public function getIdentities()
    {
        return $this->identities;
    }

    /**
     * @return IdentityInterface
     */
    public function getCurrentIdentity()
    {
        if ($this->currentIdentity === null) {
            $this->switchIdentity();
        }
        return $this->currentIdentity;
    }

    /**
     * Switches the current identity to a randomly chosen one.
     */
    public function switchIdentity()
    {
        $key = $this->randomizer->randKey($this->identities);
        $this->currentIdentity = $this->identities[$key];
        $this->randomCounter->restart();
    }

    /**
     * @param RequestInterface $request
     * @return RequestInterface
     */
    public function setupRequest(RequestInterface $request)
    {
        $identitiy = $this->getCurrentIdentity();
        if ($identitiy->getCookieJar() != null) {
            //todo
            // this seems pretty hacky... is there a better way to replace the cookie container of a request?
            // > Currently not @see https://github.com/guzzle/guzzle/issues/1028#issuecomment-96253542 - maybe with Guzzle 6

            // remove current cookie subscribers
            $emitter = $request->getEmitter();
            foreach ($emitter->listeners("complete") as $listener) {
                if (is_array($listener) && $listener[0] instanceof Cookie) {
                    $emitter->detach($listener[0]);
                }
            }
            // set new Cookie subscriber
            $cookie = new Cookie($identitiy->getCookieJar());
            $emitter->attach($cookie);
        }
        if ($identitiy->getUserAgent() != null) {
            $request->setHeader("user-agent", $identitiy->getUserAgent());
        }
        $headers = $identitiy->getDefaultRequestHeaders();
        if ($headers != null) {
            foreach ($headers as $key => $val) {
                $request->setHeader($key, $val);
            }
        }
        if ($identitiy->getReferer() != null && trim($identitiy->getReferer()) != "") {
            $request->setHeader("referer", $identitiy->getReferer());
        }
        $request = parent::setupRequest($request);
        return $request;
    }
}