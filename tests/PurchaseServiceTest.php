<?php

namespace SilverStripe\Omnipay\Tests;

use SilverStripe\Omnipay\Service\PurchaseService;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Tests\Extensions\PaymentTestServiceExtensionHooks;

class PurchaseServiceTest extends BasePurchaseServiceTest
{
    protected $completeStatus = 'Captured';
    protected $pendingStatus = 'PendingPurchase';

    protected $omnipayMethod = 'purchase';
    protected $omnipayCompleteMethod = 'completePurchase';

    protected $onsiteSuccessMessages = [
        ['Type' => PurchaseService::MESSAGE_PURCHASE_REQUEST],
        ['Type' => PurchaseService::MESSAGE_PURCHASED_RESPONSE]
    ];

    protected $onsiteFailMessages = [
        ['Type' => PurchaseService::MESSAGE_PURCHASE_REQUEST],
        ['Type' => PurchaseService::MESSAGE_PURCHASE_ERROR]
    ];

    protected $failMessages = [
        ['Type' => PurchaseService::MESSAGE_PURCHASE_ERROR]
    ];

    protected $offsiteSuccessMessages = [
        ['Type' => PurchaseService::MESSAGE_PURCHASE_REQUEST],
        ['Type' => PurchaseService::MESSAGE_PURCHASE_REDIRECT_RESPONSE],
        ['Type' => PurchaseService::MESSAGE_COMPLETE_PURCHASE_REQUEST],
        ['Type' => PurchaseService::MESSAGE_PURCHASED_RESPONSE]
    ];

    protected $offsiteFailMessages = [
        ['Type' => PurchaseService::MESSAGE_PURCHASE_REQUEST],
        ['Type' => PurchaseService::MESSAGE_PURCHASE_REDIRECT_RESPONSE],
        ['Type' => PurchaseService::MESSAGE_COMPLETE_PURCHASE_REQUEST],
        ['Type' => PurchaseService::MESSAGE_COMPLETE_PURCHASE_ERROR]
    ];

    protected $failureMessageType = PurchaseService::MESSAGE_COMPLETE_PURCHASE_ERROR;

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
