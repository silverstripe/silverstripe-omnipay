<?php

namespace SilverStripe\Omnipay\Tests\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Dev\TestOnly;

/**
 * Extension that can be used to test hooks on payment services
 */
class PaymentTestServiceExtensionHooks extends Extension implements TestOnly
{
    protected $callStack = [];

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

    public function updateServiceResponse($serviceResponse)
    {
        $this->callStack[] = [
            'method' => 'updateServiceResponse',
            'args' => [$serviceResponse]
        ];
    }

    public function updatePartialPayment($newPayment, $originalPayment)
    {
        $this->callStack[] = [
            'method' => 'updatePartialPayment',
            'args' => [$newPayment, $originalPayment]
        ];
    }

    public function onBeforeAuthorize($data)
    {
        $this->callStack[] = [
            'method' => 'onBeforeAuthorize',
            'args' => [$data]
        ];
    }

    public function onBeforeCapture($data)
    {
        $this->callStack[] = [
            'method' => 'onBeforeCapture',
            'args' => [$data]
        ];
    }

    public function onBeforePurchase($data)
    {
        $this->callStack[] = [
            'method' => 'onBeforePurchase',
            'args' => [$data]
        ];
    }

    public function onBeforeRefund($data)
    {
        $this->callStack[] = [
            'method' => 'onBeforeRefund',
            'args' => [$data]
        ];
    }

    public function onBeforeVoid($data)
    {
        $this->callStack[] = [
            'method' => 'onBeforeVoid',
            'args' => [$data]
        ];
    }

    public function onBeforeCompleteAuthorize($data)
    {
        $this->callStack[] = [
            'method' => 'onBeforeCompleteAuthorize',
            'args' => [$data]
        ];
    }

    public function onBeforeCompletePurchase($data)
    {
        $this->callStack[] = [
            'method' => 'onBeforeCompletePurchase',
            'args' => [$data]
        ];
    }

    public function onAfterAuthorize($omnipayRequest)
    {
        $this->callStack[] = [
            'method' => 'onAfterAuthorize',
            'args' => [$omnipayRequest]
        ];
    }

    public function onAfterCapture($omnipayRequest)
    {
        $this->callStack[] = [
            'method' => 'onAfterCapture',
            'args' => [$omnipayRequest]
        ];
    }

    public function onAfterPurchase($omnipayRequest)
    {
        $this->callStack[] = [
            'method' => 'onAfterPurchase',
            'args' => [$omnipayRequest]
        ];
    }

    public function onAfterRefund($omnipayRequest)
    {
        $this->callStack[] = [
            'method' => 'onAfterRefund',
            'args' => [$omnipayRequest]
        ];
    }

    public function onAfterVoid($omnipayRequest)
    {
        $this->callStack[] = [
            'method' => 'onAfterVoid',
            'args' => [$omnipayRequest]
        ];
    }

    public function onAfterCompletePurchase($omnipayRequest)
    {
        $this->callStack[] = [
            'method' => 'onAfterCompletePurchase',
            'args' => [$omnipayRequest]
        ];
    }

    public function onAfterCompleteAuthorize($omnipayRequest)
    {
        $this->callStack[] = [
            'method' => 'onAfterCompleteAuthorize',
            'args' => [$omnipayRequest]
        ];
    }

    public function onAfterSendAuthorize($omnipayRequest, $omnipayResponse)
    {
        $this->callStack[] = [
            'method' => 'onAfterSendAuthorize',
            'args' => [$omnipayRequest, $omnipayResponse]
        ];
    }

    public function onAfterSendCapture($omnipayRequest, $omnipayResponse)
    {
        $this->callStack[] = [
            'method' => 'onAfterSendCapture',
            'args' => [$omnipayRequest, $omnipayResponse]
        ];
    }

    public function onAfterSendPurchase($omnipayRequest, $omnipayResponse)
    {
        $this->callStack[] = [
            'method' => 'onAfterSendPurchase',
            'args' => [$omnipayRequest, $omnipayResponse]
        ];
    }

    public function onAfterSendRefund($omnipayRequest, $omnipayResponse)
    {
        $this->callStack[] = [
            'method' => 'onAfterSendRefund',
            'args' => [$omnipayRequest, $omnipayResponse]
        ];
    }

    public function onAfterSendVoid($omnipayRequest, $omnipayResponse)
    {
        $this->callStack[] = [
            'method' => 'onAfterSendVoid',
            'args' => [$omnipayRequest, $omnipayResponse]
        ];
    }

    public function onBeforeCreateCard($data)
    {
        $this->callStack[] = [
            'method' => 'onBeforeCreateCard',
            'args' => [$data]
        ];
    }

    public function onAfterCreateCard($omnipayRequest)
    {
        $this->callStack[] = [
            'method' => 'onAfterCreateCard',
            'args' => [$omnipayRequest]
        ];
    }

    public function onAfterSendCreateCard($omnipayRequest, $omnipayResponse)
    {
        $this->callStack[] = [
            'method' => 'onAfterSendCreateCard',
            'args' => [$omnipayRequest, $omnipayResponse]
        ];
    }

    public function onBeforeCompleteCreateCard($data)
    {
        $this->callStack[] = [
            'method' => 'onBeforeCompleteCreateCard',
            'args' => [$data]
        ];
    }

    public function onAfterCompleteCreateCard($omnipayRequest)
    {
        $this->callStack[] = [
            'method' => 'onAfterCompleteCreateCard',
            'args' => [$omnipayRequest]
        ];
    }
}
