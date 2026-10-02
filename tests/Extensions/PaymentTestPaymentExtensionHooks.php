<?php

namespace SilverStripe\Omnipay\Tests\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Dev\TestOnly;

/**
 * Extension that can be used to test payment hooks
 * @codeCoverageIgnore
 * @extends Extension<static>
 */
class PaymentTestPaymentExtensionHooks extends Extension implements TestOnly
{
    protected static array $instances = [];

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

    public static function ResetAll(): void
    {
        foreach (self::$instances as $instance) {
            $instance->Reset();
        }

        self::$instances = [];
    }

    protected $callStack = [];

    public function setOwner($owner): void
    {
        parent::setOwner($owner);

        if ($owner) {
            self::$instances[$owner->ID] = $this;
        }
    }

    public function Reset(): void
    {
        $this->callStack = [];
    }

    /**
     * Get an array of the extension methods that were called and their arguments
     */
    public function getCallStack(): array
    {
        return $this->callStack;
    }

    /**
     * Get an array of the extension methods that were called
     */
    public function getCalledMethods(): array
    {
        $result = [];
        array_walk($this->callStack, function (array $value, $key) use (&$result): void {
            $result[] = $value['method'];
        });
        return $result;
    }



    public function onAuthorized($serviceResponse): void
    {
        $this->callStack[] = [
            'method' => 'onAuthorized',
            'args' => [$serviceResponse]
        ];
    }

    public function onAwaitingAuthorized($serviceResponse): void
    {
        $this->callStack[] = [
            'method' => 'onAwaitingAuthorized',
            'args' => [$serviceResponse]
        ];
    }

    public function onCaptured($serviceResponse): void
    {
        $this->callStack[] = [
            'method' => 'onCaptured',
            'args' => [$serviceResponse]
        ];
    }

    public function onAwaitingCaptured($serviceResponse): void
    {
        $this->callStack[] = [
            'method' => 'onAwaitingCaptured',
            'args' => [$serviceResponse]
        ];
    }

    public function onRefunded($serviceResponse): void
    {
        $this->callStack[] = [
            'method' => 'onRefunded',
            'args' => [$serviceResponse]
        ];
    }

    public function onVoid($serviceResponse): void
    {
        $this->callStack[] = [
            'method' => 'onVoid',
            'args' => [$serviceResponse]
        ];
    }

    public function onCancelled(): void
    {
        $this->callStack[] = [
            'method' => 'onCancelled',
            'args' => []
        ];
    }

    public function onCardCreated($serviceResponse): void
    {
        $this->callStack[] = [
            'method' => 'onCardCreated',
            'args' => [$serviceResponse]
        ];
    }

    public function onAwaitingCreateCard($serviceResponse): void
    {
        $this->callStack[] = [
            'method' => 'onAwaitingCreateCard',
            'args' => [$serviceResponse]
        ];
    }
}
