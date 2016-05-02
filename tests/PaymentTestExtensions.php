<?php

/**
 * Extension that can be used to test payment hooks
 * @codeCoverageIgnore
 */
class PaymentTest_PaymentExtensionHooks extends DataExtension implements TestOnly
{
    protected static $instances = array();

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
        self::$instances = array();
    }

    protected $callStack = array();

    public function setOwner($owner, $ownerBaseClass = null)
    {
        parent::setOwner($owner, $ownerBaseClass);

        if ($owner) {
            self::$instances[$owner->ID] = $this;
        }
    }

    public function Reset()
    {
        $this->callStack = array();
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
        $result = array();
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
            'args' => array()
        );
    }
}

/**
 * Extension that can be used to test hooks on payment services
 */
class PaymentTest_ServiceExtensionHooks extends Extension implements TestOnly
{
    protected $callStack = array();

    public function Reset()
    {
        $this->callStack = array();
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
        $result = array();
        array_walk($this->callStack, function ($value, $key) use (&$result) {
            $result[] = $value['method'];
        });
        return $result;
    }

    public function updateServiceResponse($serviceResponse)
    {
        $this->callStack[] = array(
            'method' => 'updateServiceResponse',
            'args' => array($serviceResponse)
        );
    }

    public function updatePartialPayment($newPayment, $originalPayment)
    {
        $this->callStack[] = array(
            'method' => 'updatePartialPayment',
            'args' => array($newPayment, $originalPayment)
        );
    }

    public function onBeforeAuthorize($data)
    {
        $this->callStack[] = array(
            'method' => 'onBeforeAuthorize',
            'args' => array($data)
        );
    }

    public function onBeforeCapture($data)
    {
        $this->callStack[] = array(
            'method' => 'onBeforeCapture',
            'args' => array($data)
        );
    }

    public function onBeforePurchase($data)
    {
        $this->callStack[] = array(
            'method' => 'onBeforePurchase',
            'args' => array($data)
        );
    }

    public function onBeforeRefund($data)
    {
        $this->callStack[] = array(
            'method' => 'onBeforeRefund',
            'args' => array($data)
        );
    }

    public function onBeforeVoid($data)
    {
        $this->callStack[] = array(
            'method' => 'onBeforeVoid',
            'args' => array($data)
        );
    }

    public function onBeforeCompleteAuthorize($data)
    {
        $this->callStack[] = array(
            'method' => 'onBeforeCompleteAuthorize',
            'args' => array($data)
        );
    }

    public function onBeforeCompletePurchase($data)
    {
        $this->callStack[] = array(
            'method' => 'onBeforeCompletePurchase',
            'args' => array($data)
        );
    }

    public function onAfterAuthorize($omnipayRequest)
    {
        $this->callStack[] = array(
            'method' => 'onAfterAuthorize',
            'args' => array($omnipayRequest)
        );
    }

    public function onAfterCapture($omnipayRequest)
    {
        $this->callStack[] = array(
            'method' => 'onAfterCapture',
            'args' => array($omnipayRequest)
        );
    }

    public function onAfterPurchase($omnipayRequest)
    {
        $this->callStack[] = array(
            'method' => 'onAfterPurchase',
            'args' => array($omnipayRequest)
        );
    }

    public function onAfterRefund($omnipayRequest)
    {
        $this->callStack[] = array(
            'method' => 'onAfterRefund',
            'args' => array($omnipayRequest)
        );
    }

    public function onAfterVoid($omnipayRequest)
    {
        $this->callStack[] = array(
            'method' => 'onAfterVoid',
            'args' => array($omnipayRequest)
        );
    }

    public function onAfterCompletePurchase($omnipayRequest)
    {
        $this->callStack[] = array(
            'method' => 'onAfterCompletePurchase',
            'args' => array($omnipayRequest)
        );
    }

    public function onAfterCompleteAuthorize($omnipayRequest)
    {
        $this->callStack[] = array(
            'method' => 'onAfterCompleteAuthorize',
            'args' => array($omnipayRequest)
        );
    }

    public function onAfterSendAuthorize($omnipayRequest, $omnipayResponse)
    {
        $this->callStack[] = array(
            'method' => 'onAfterSendAuthorize',
            'args' => array($omnipayRequest, $omnipayResponse)
        );
    }

    public function onAfterSendCapture($omnipayRequest, $omnipayResponse)
    {
        $this->callStack[] = array(
            'method' => 'onAfterSendCapture',
            'args' => array($omnipayRequest, $omnipayResponse)
        );
    }

    public function onAfterSendPurchase($omnipayRequest, $omnipayResponse)
    {
        $this->callStack[] = array(
            'method' => 'onAfterSendPurchase',
            'args' => array($omnipayRequest, $omnipayResponse)
        );
    }

    public function onAfterSendRefund($omnipayRequest, $omnipayResponse)
    {
        $this->callStack[] = array(
            'method' => 'onAfterSendRefund',
            'args' => array($omnipayRequest, $omnipayResponse)
        );
    }

    public function onAfterSendVoid($omnipayRequest, $omnipayResponse)
    {
        $this->callStack[] = array(
            'method' => 'onAfterSendVoid',
            'args' => array($omnipayRequest, $omnipayResponse)
        );
    }
}
