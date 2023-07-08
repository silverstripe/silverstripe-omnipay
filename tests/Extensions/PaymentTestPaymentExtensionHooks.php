<?php

namespace SilverStripe\Omnipay\Tests\Extensions;

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
     * Fint the PaymentTestPaymentExtensionHooks instance for a given payment ID
     * @param $id
     * @return PaymentTestPaymentExtensionHooks|null
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
        $this->callStack[] = [
            'method' => 'onAuthorized',
            'args' => [$serviceResponse]
        ];
    }

    public function onAwaitingAuthorized($serviceResponse)
    {
        $this->callStack[] = [
            'method' => 'onAwaitingAuthorized',
            'args' => [$serviceResponse]
        ];
    }

    public function onCaptured($serviceResponse)
    {
        $this->callStack[] = [
            'method' => 'onCaptured',
            'args' => [$serviceResponse]
        ];
    }

    public function onAwaitingCaptured($serviceResponse)
    {
        $this->callStack[] = [
            'method' => 'onAwaitingCaptured',
            'args' => [$serviceResponse]
        ];
    }

    public function onRefunded($serviceResponse)
    {
        $this->callStack[] = [
            'method' => 'onRefunded',
            'args' => [$serviceResponse]
        ];
    }

    public function onVoid($serviceResponse)
    {
        $this->callStack[] = [
            'method' => 'onVoid',
            'args' => [$serviceResponse]
        ];
    }

    public function onCancelled()
    {
        $this->callStack[] = [
            'method' => 'onCancelled',
            'args' => []
        ];
    }
    public function onCardCreated($serviceResponse)
    {
        $this->callStack[] = [
            'method' => 'onCardCreated',
            'args' => [$serviceResponse]
        ];
    }

    public function onAwaitingCreateCard($serviceResponse)
    {
        $this->callStack[] = [
            'method' => 'onAwaitingCreateCard',
            'args' => [$serviceResponse]
        ];
    }
}
