<?php

declare(strict_types=1);

namespace SilverStripe\Omnipay\Tests\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Dev\TestOnly;

/**
 * Extension that can be used to test hooks on payment services
 * @extends Extension<static>
 */
class PaymentTestServiceExtensionHooks extends Extension implements TestOnly
{
    protected array $callStack = [];

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

    public function updateServiceResponse($serviceResponse): void
    {
        $this->callStack[] = [
            'method' => 'updateServiceResponse',
            'args' => [$serviceResponse]
        ];
    }

    public function updatePartialPayment($newPayment, $originalPayment): void
    {
        $this->callStack[] = [
            'method' => 'updatePartialPayment',
            'args' => [$newPayment, $originalPayment]
        ];
    }

    public function onBeforeAuthorize($data): void
    {
        $this->callStack[] = [
            'method' => 'onBeforeAuthorize',
            'args' => [$data]
        ];
    }

    public function onBeforeCapture($data): void
    {
        $this->callStack[] = [
            'method' => 'onBeforeCapture',
            'args' => [$data]
        ];
    }

    public function onBeforePurchase($data): void
    {
        $this->callStack[] = [
            'method' => 'onBeforePurchase',
            'args' => [$data]
        ];
    }

    public function onBeforeRefund($data): void
    {
        $this->callStack[] = [
            'method' => 'onBeforeRefund',
            'args' => [$data]
        ];
    }

    public function onBeforeVoid($data): void
    {
        $this->callStack[] = [
            'method' => 'onBeforeVoid',
            'args' => [$data]
        ];
    }

    public function onBeforeCompleteAuthorize($data): void
    {
        $this->callStack[] = [
            'method' => 'onBeforeCompleteAuthorize',
            'args' => [$data]
        ];
    }

    public function onBeforeCompletePurchase($data): void
    {
        $this->callStack[] = [
            'method' => 'onBeforeCompletePurchase',
            'args' => [$data]
        ];
    }

    public function onAfterAuthorize($omnipayRequest): void
    {
        $this->callStack[] = [
            'method' => 'onAfterAuthorize',
            'args' => [$omnipayRequest]
        ];
    }

    public function onAfterCapture($omnipayRequest): void
    {
        $this->callStack[] = [
            'method' => 'onAfterCapture',
            'args' => [$omnipayRequest]
        ];
    }

    public function onAfterPurchase($omnipayRequest): void
    {
        $this->callStack[] = [
            'method' => 'onAfterPurchase',
            'args' => [$omnipayRequest]
        ];
    }

    public function onAfterRefund($omnipayRequest): void
    {
        $this->callStack[] = [
            'method' => 'onAfterRefund',
            'args' => [$omnipayRequest]
        ];
    }

    public function onAfterVoid($omnipayRequest): void
    {
        $this->callStack[] = [
            'method' => 'onAfterVoid',
            'args' => [$omnipayRequest]
        ];
    }

    public function onAfterCompletePurchase($omnipayRequest): void
    {
        $this->callStack[] = [
            'method' => 'onAfterCompletePurchase',
            'args' => [$omnipayRequest]
        ];
    }

    public function onAfterCompleteAuthorize($omnipayRequest): void
    {
        $this->callStack[] = [
            'method' => 'onAfterCompleteAuthorize',
            'args' => [$omnipayRequest]
        ];
    }

    public function onAfterSendAuthorize($omnipayRequest, $omnipayResponse): void
    {
        $this->callStack[] = [
            'method' => 'onAfterSendAuthorize',
            'args' => [$omnipayRequest, $omnipayResponse]
        ];
    }

    public function onAfterSendCapture($omnipayRequest, $omnipayResponse): void
    {
        $this->callStack[] = [
            'method' => 'onAfterSendCapture',
            'args' => [$omnipayRequest, $omnipayResponse]
        ];
    }

    public function onAfterSendPurchase($omnipayRequest, $omnipayResponse): void
    {
        $this->callStack[] = [
            'method' => 'onAfterSendPurchase',
            'args' => [$omnipayRequest, $omnipayResponse]
        ];
    }

    public function onAfterSendRefund($omnipayRequest, $omnipayResponse): void
    {
        $this->callStack[] = [
            'method' => 'onAfterSendRefund',
            'args' => [$omnipayRequest, $omnipayResponse]
        ];
    }

    public function onAfterSendVoid($omnipayRequest, $omnipayResponse): void
    {
        $this->callStack[] = [
            'method' => 'onAfterSendVoid',
            'args' => [$omnipayRequest, $omnipayResponse]
        ];
    }

    public function onBeforeCreateCard($data): void
    {
        $this->callStack[] = [
            'method' => 'onBeforeCreateCard',
            'args' => [$data]
        ];
    }

    public function onAfterCreateCard($omnipayRequest): void
    {
        $this->callStack[] = [
            'method' => 'onAfterCreateCard',
            'args' => [$omnipayRequest]
        ];
    }

    public function onAfterSendCreateCard($omnipayRequest, $omnipayResponse): void
    {
        $this->callStack[] = [
            'method' => 'onAfterSendCreateCard',
            'args' => [$omnipayRequest, $omnipayResponse]
        ];
    }

    public function onBeforeCompleteCreateCard($data): void
    {
        $this->callStack[] = [
            'method' => 'onBeforeCompleteCreateCard',
            'args' => [$data]
        ];
    }

    public function onAfterCompleteCreateCard($omnipayRequest): void
    {
        $this->callStack[] = [
            'method' => 'onAfterCompleteCreateCard',
            'args' => [$omnipayRequest]
        ];
    }
}
