<?php

namespace SilverStripe\Omnipay\Tests;

use SilverStripe\Omnipay\Service\AuthorizeService;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Tests\Extensions\PaymentTestServiceExtensionHooks;
use SilverStripe\Core\Config\Config;
use SilverStripe\Omnipay\Model\Message;

class AuthorizeServiceTest extends BasePurchaseServiceTest
{
    protected $completeStatus = 'Authorized';

    protected $pendingStatus = 'PendingAuthorization';

    protected $omnipayMethod = 'authorize';

    protected $omnipayCompleteMethod = 'completeAuthorize';

    protected $onsiteSuccessMessages = [
        ['ClassName' => Message\AuthorizeRequest::class],
        ['ClassName' => Message\AuthorizedResponse::class]
    ];

    protected $onsiteFailMessages = [
        ['ClassName' => Message\AuthorizeRequest::class],
        ['ClassName' => Message\AuthorizeError::class]
    ];

    protected $failMessages = [
        ['ClassName' => Message\AuthorizeError::class]
    ];

    protected $offsiteSuccessMessages = [
        ['ClassName' => Message\AuthorizeRequest::class],
        ['ClassName' => Message\AuthorizeRedirectResponse::class],
        ['ClassName' => Message\CompleteAuthorizeRequest::class],
        ['ClassName' => Message\AuthorizedResponse::class]
    ];

    protected $offsiteFailMessages = [
        ['ClassName' => Message\AuthorizeRequest::class],
        ['ClassName' => Message\AuthorizeRedirectResponse::class],
        ['ClassName' => Message\CompleteAuthorizeRequest::class],
        ['ClassName' => Message\CompleteAuthorizeError::class]
    ];

    protected $failureMessageClass = Message\CompleteAuthorizeError::class;

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

        Config::modify()->merge(GatewayInfo::class, 'PaymentExpress_PxPay', [
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
