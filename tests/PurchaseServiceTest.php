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

    protected $onsiteSuccessMessages = array(
        array('ClassName' => Message\PurchaseRequest::class),
        array('ClassName' => Message\PurchasedResponse::class)
    );

    protected $onsiteFailMessages = array(
        array('ClassName' => Message\PurchaseRequest::class),
        array('ClassName' => Message\PurchaseError::class)
    );

    protected $failMessages = array(
        array('ClassName' => Message\PurchaseError::class)
    );

    protected $offsiteSuccessMessages = array(
        array('ClassName' => Message\PurchaseRequest::class),
        array('ClassName' => Message\PurchaseRedirectResponse::class),
        array('ClassName' => Message\CompletePurchaseRequest::class),
        array('ClassName' => Message\PurchasedResponse::class)
    );

    protected $offsiteFailMessages = array(
        array('ClassName' => Message\PurchaseRequest::class),
        array('ClassName' => Message\PurchaseRedirectResponse::class),
        array('ClassName' => Message\CompletePurchaseRequest::class),
        array('ClassName' => Message\CompletePurchaseError::class)
    );

    protected $failureMessageClass = Message\CompletePurchaseError::class;

    protected $paymentId = 'UNIQUEHASH23q5123tqasdf';

    protected $successPaymentExtensionHooks = array(
        'onCaptured'
    );

    protected $notifyPaymentExtensionHooks = array(
        'onAwaitingCaptured'
    );

    protected $initiateServiceExtensionHooks = array(
        'onBeforePurchase',
        'onAfterPurchase',
        'onAfterSendPurchase',
        'updateServiceResponse'
    );

    protected $initiateFailedServiceExtensionHooks = array(
        'onBeforePurchase',
        'onAfterPurchase',
        'updateServiceResponse'
    );

    protected $completeServiceExtensionHooks = array(
        'onBeforeCompletePurchase',
        'onAfterCompletePurchase',
        'updateServiceResponse'
    );

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
