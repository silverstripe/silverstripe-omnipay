<?php

namespace SilverStripe\Omnipay\Tests;

use SilverStripe\Omnipay\Service\AuthorizeService;
use SilverStripe\Omnipay\Tests\BasePurchaseServiceTest;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Tests\Extensions\PaymentTestServiceExtensionHooks;

class AuthorizeServiceTest extends BasePurchaseServiceTest
{
    protected $completeStatus = 'Authorized';

    protected $pendingStatus = 'PendingAuthorization';

    protected $omnipayMethod = 'authorize';

    protected $omnipayCompleteMethod = 'completeAuthorize';

    protected $onsiteSuccessMessages = [
        ['ClassName' => 'AuthorizeRequest'],
        ['ClassName' => 'AuthorizedResponse']
    ];

    protected $onsiteFailMessages = [
        ['ClassName' => 'AuthorizeRequest'],
        ['ClassName' => 'AuthorizeError']
    ];

    protected $failMessages = [
        ['ClassName' => 'AuthorizeError']
    ];

    protected $offsiteSuccessMessages = [
        ['ClassName' => 'AuthorizeRequest'],
        ['ClassName' => 'AuthorizeRedirectResponse'],
        ['ClassName' => 'CompleteAuthorizeRequest'],
        ['ClassName' => 'AuthorizedResponse']
    ];

    protected $offsiteFailMessages = [
        ['ClassName' => 'AuthorizeRequest'],
        ['ClassName' => 'AuthorizeRedirectResponse'],
        ['ClassName' => 'CompleteAuthorizeRequest'],
        ['ClassName' => 'CompleteAuthorizeError']
    ];

    protected $failureMessageClass = 'CompleteAuthorizeError';

    protected $paymentId = '62b26e0a8a77f60cce3e9a7994087b0e';

    protected $successPaymentExtensionHooks = [
        'onAuthorized'
    ];

    protected $notifyPaymentExtensionHooks = [
        'onAwaitingAuthorized'
    ];

    protected $initiateServiceExtensionHooks = [
        'onBeforeAuthorize',
        'onAfterAuthorize',
        'onAfterSendAuthorize',
        'updateServiceResponse'
    ];

    protected $initiateFailedServiceExtensionHooks = [
        'onBeforeAuthorize',
        'onAfterAuthorize',
        'updateServiceResponse'
    ];

    protected $completeServiceExtensionHooks = [
        'onBeforeCompleteAuthorize',
        'onAfterCompleteAuthorize',
        'updateServiceResponse'
    ];

    public function setUp()
    {
        parent::setUp();

        AuthorizeService::add_extension(PaymentTestServiceExtensionHooks::class);

        Config::modify()->set(GatewayInfo::class, 'PaymentExpress_PxPay', [
            'use_authorize' => true
        ]);
    }

    public function tearDown()
    {
        parent::tearDown();

        AuthorizeService::remove_extension(PaymentTestServiceExtensionHooks::class);
    }

    protected function getService(Payment $payment)
    {
        return AuthorizeService::create($payment);
    }
}
