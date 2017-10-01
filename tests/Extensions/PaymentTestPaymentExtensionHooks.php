<?php

namespace SilverStripe\Omnipay\Tests;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Dev\TestOnly;

/**
 * Extension that can be used to test payment hooks
 * @codeCoverageIgnore
 */
class PaymentTestPaymentExtensionHooks extends DataExtension implements TestOnly
{
    protected static $instances = [];

    /**
     * Fint the PaymentTest_PaymentExtensionHooks instance for a given payment ID
     * @param $id
     * @return PaymentTest_PaymentExtensionHooks|null
     */
    public static function findExtensionForID($id)
    {
        if (empty(self::$instances[$id])) {
            return null;
        }

        return self::$instances[$id];
    }

    public static function ResetAll()
    {
        foreach (self::$instances as $instance) {
            $instance->Reset();
        }
        self::$instances = [];
    }

    protected $callStack = [];

    public function setOwner($owner, $ownerBaseClass = null)
    {
        parent::setOwner($owner, $ownerBaseClass);

        if ($owner) {
            self::$instances[$owner->ID] = $this;
        }
    }

    public function Reset()
    {
        $this->callStack = [];
    }

    /**
     * Get an array of the extension methods that were called and their arguments
     * @return array
     */
    public function getCallStack()
    {
        return $this->callStack;
    }

    /**
     * Get an array of the extension methods that were called
     * @return array
     */
    public function getCalledMethods()
    {
        $result = [];
        array_walk($this->callStack, function ($value, $key) use (&$result) {
            $result[] = $value['method'];
        });
        return $result;
    }



    public function onAuthorized($serviceResponse)
    {
        $this->callStack[] = array(
            'method' => 'onAuthorized',
            'args' => array($serviceResponse)
        );
    }

    public function onAwaitingAuthorized($serviceResponse)
    {
        $this->callStack[] = array(
            'method' => 'onAwaitingAuthorized',
            'args' => array($serviceResponse)
        );
    }

    public function onCaptured($serviceResponse)
    {
        $this->callStack[] = array(
            'method' => 'onCaptured',
            'args' => array($serviceResponse)
        );
    }

    public function onAwaitingCaptured($serviceResponse)
    {
        $this->callStack[] = array(
            'method' => 'onAwaitingCaptured',
            'args' => array($serviceResponse)
        );
    }

    public function onRefunded($serviceResponse)
    {
        $this->callStack[] = array(
            'method' => 'onRefunded',
            'args' => array($serviceResponse)
        );
    }

    public function onVoid($serviceResponse)
    {
        $this->callStack[] = array(
            'method' => 'onVoid',
            'args' => array($serviceResponse)
        );
    }

    public function onCancelled()
    {
        $this->callStack[] = array(
            'method' => 'onCancelled',
            'args' => []
        );
    }
    public function onCardCreated($serviceResponse)
    {
        $this->callStack[] = array(
            'method' => 'onCardCreated',
            'args' => array($serviceResponse)
        );
    }

    public function onAwaitingCreateCard($serviceResponse)
    {
        $this->callStack[] = array(
            'method' => 'onAwaitingCreateCard',
            'args' => array($serviceResponse)
        );
    }
}
