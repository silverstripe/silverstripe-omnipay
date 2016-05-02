<?php

use SilverStripe\Omnipay\Service\AuthorizeService;

class AuthorizeServiceTest extends BasePurchaseServiceTest
{
    protected $completeStatus = 'Authorized';
    protected $pendingStatus = 'PendingAuthorization';

    protected $omnipayMethod = 'authorize';
    protected $omnipayCompleteMethod = 'completeAuthorize';

    protected $onsiteSuccessMessages = array(
        array('ClassName' => 'AuthorizeRequest'),
        array('ClassName' => 'AuthorizedResponse')
    );

    protected $onsiteFailMessages = array(
        array('ClassName' => 'AuthorizeRequest'),
        array('ClassName' => 'AuthorizeError')
    );

    protected $failMessages = array(
        array('ClassName' => 'AuthorizeError')
    );

    protected $offsiteSuccessMessages = array(
        array('ClassName' => 'AuthorizeRequest'),
        array('ClassName' => 'AuthorizeRedirectResponse'),
        array('ClassName' => 'CompleteAuthorizeRequest'),
        array('ClassName' => 'AuthorizedResponse')
    );

    protected $offsiteFailMessages = array(
        array('ClassName' => 'AuthorizeRequest'),
        array('ClassName' => 'AuthorizeRedirectResponse'),
        array('ClassName' => 'CompleteAuthorizeRequest'),
        array('ClassName' => 'CompleteAuthorizeError')
    );

    protected $failureMessageClass = 'CompleteAuthorizeError';

    protected $paymentId = '62b26e0a8a77f60cce3e9a7994087b0e';

    protected $successPaymentExtensionHooks = array(
        'onAuthorized'
    );

    protected $notifyPaymentExtensionHooks = array(
        'onAwaitingAuthorized'
    );

    protected $initiateServiceExtensionHooks = array(
        'onBeforeAuthorize',
        'onAfterAuthorize',
        'onAfterSendAuthorize',
        'updateServiceResponse'
    );

    protected $initiateFailedServiceExtensionHooks = array(
        'onBeforeAuthorize',
        'onAfterAuthorize',
        'updateServiceResponse'
    );

    protected $completeServiceExtensionHooks = array(
        'onBeforeCompleteAuthorize',
        'onAfterCompleteAuthorize',
        'updateServiceResponse'
    );

    public function setUp()
    {
        parent::setUp();
        AuthorizeService::add_extension('PaymentTest_ServiceExtensionHooks');

        Config::inst()->update('GatewayInfo', 'PaymentExpress_PxPay', array(
            'use_authorize' => true
        ));
    }

    public function tearDown()
    {
        parent::tearDown();
        AuthorizeService::remove_extension('PaymentTest_ServiceExtensionHooks');
    }

    protected function getService(Payment $payment)
    {
        return AuthorizeService::create($payment);
    }
}
