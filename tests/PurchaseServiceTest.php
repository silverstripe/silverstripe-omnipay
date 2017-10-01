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

    protected $onsiteSuccessMessages = array(
        array('ClassName' => 'PurchaseRequest'),
        array('ClassName' => 'PurchasedResponse')
    );

    protected $onsiteFailMessages = array(
        array('ClassName' => 'PurchaseRequest'),
        array('ClassName' => 'PurchaseError')
    );

    protected $failMessages = array(
        array('ClassName' => 'PurchaseError')
    );

    protected $offsiteSuccessMessages = array(
        array('ClassName' => 'PurchaseRequest'),
        array('ClassName' => 'PurchaseRedirectResponse'),
        array('ClassName' => 'CompletePurchaseRequest'),
        array('ClassName' => 'PurchasedResponse')
    );

    protected $offsiteFailMessages = array(
        array('ClassName' => 'PurchaseRequest'),
        array('ClassName' => 'PurchaseRedirectResponse'),
        array('ClassName' => 'CompletePurchaseRequest'),
        array('ClassName' => 'CompletePurchaseError')
    );

    protected $failureMessageClass = 'CompletePurchaseError';

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

    public function setUp()
    {
        parent::setUp();
        PurchaseService::add_extension(PaymentTestServiceExtensionHooks::class);
    }

    public function tearDown()
    {
        parent::tearDown();
        PurchaseService::remove_extension(PaymentTestServiceExtensionHooks::class);
    }

    protected function getService(Payment $payment)
    {
        return PurchaseService::create($payment);
    }
}
