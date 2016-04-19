<?php

use SilverStripe\Omnipay\Service\PurchaseService;

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

    protected function getService(Payment $payment)
    {
        return PurchaseService::create($payment);
    }
}
