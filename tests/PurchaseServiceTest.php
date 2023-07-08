<?php

namespace SilverStripe\Omnipay\Tests;

use SilverStripe\Omnipay\Service\PurchaseService;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Model\Message;
use SilverStripe\Omnipay\Tests\Extensions\PaymentTestServiceExtensionHooks;

class PurchaseServiceTest extends BasePurchaseServiceTest
{
    protected $completeStatus = 'Captured';
    protected $pendingStatus = 'PendingPurchase';

    protected $omnipayMethod = 'purchase';
    protected $omnipayCompleteMethod = 'completePurchase';

    protected $onsiteSuccessMessages = [
        ['ClassName' => Message\PurchaseRequest::class],
        ['ClassName' => Message\PurchasedResponse::class]
    ];

    protected $onsiteFailMessages = [
        ['ClassName' => Message\PurchaseRequest::class],
        ['ClassName' => Message\PurchaseError::class]
    ];

    protected $failMessages = [
        ['ClassName' => Message\PurchaseError::class]
    ];

    protected $offsiteSuccessMessages = [
        ['ClassName' => Message\PurchaseRequest::class],
        ['ClassName' => Message\PurchaseRedirectResponse::class],
        ['ClassName' => Message\CompletePurchaseRequest::class],
        ['ClassName' => Message\PurchasedResponse::class]
    ];

    protected $offsiteFailMessages = [
        ['ClassName' => Message\PurchaseRequest::class],
        ['ClassName' => Message\PurchaseRedirectResponse::class],
        ['ClassName' => Message\CompletePurchaseRequest::class],
        ['ClassName' => Message\CompletePurchaseError::class]
    ];

    protected $failureMessageClass = Message\CompletePurchaseError::class;

    protected $paymentId = 'UNIQUEHASH23q5123tqasdf';

    protected $successPaymentExtensionHooks = [
        'onCaptured'
    ];

    protected $notifyPaymentExtensionHooks = [
        'onAwaitingCaptured'
    ];

    protected $initiateServiceExtensionHooks = [
        'onBeforePurchase',
        'onAfterPurchase',
        'onAfterSendPurchase',
        'updateServiceResponse'
    ];

    protected $initiateFailedServiceExtensionHooks = [
        'onBeforePurchase',
        'onAfterPurchase',
        'updateServiceResponse'
    ];

    protected $completeServiceExtensionHooks = [
        'onBeforeCompletePurchase',
        'onAfterCompletePurchase',
        'updateServiceResponse'
    ];

    public function setUp(): void
    {
        parent::setUp();

        PurchaseService::add_extension(PaymentTestServiceExtensionHooks::class);
    }

    public function tearDown(): void
    {
        parent::tearDown();

        PurchaseService::remove_extension(PaymentTestServiceExtensionHooks::class);
    }

    protected function getService(Payment $payment)
    {
        return PurchaseService::create($payment);
    }
}
