<?php

namespace SilverStripe\Omnipay\Tests;

use SilverStripe\Omnipay\Service\AuthorizeService;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Tests\Extensions\PaymentTestServiceExtensionHooks;
use SilverStripe\Core\Config\Config;
use SilverStripe\Omnipay\GatewayInfo;

class AuthorizeServiceTest extends BasePurchaseServiceTest
{
    protected $completeStatus = 'Authorized';

    protected $pendingStatus = 'PendingAuthorization';

    protected $omnipayMethod = 'authorize';

    protected $omnipayCompleteMethod = 'completeAuthorize';

    protected $onsiteSuccessMessages = [
        ['Type' => AuthorizeService::MESSAGE_AUTHORIZE_REQUEST],
        ['Type' => AuthorizeService::MESSAGE_AUTHORIZED_RESPONSE]
    ];

    protected $onsiteFailMessages = [
        ['Type' => AuthorizeService::MESSAGE_AUTHORIZE_REQUEST],
        ['Type' => AuthorizeService::MESSAGE_AUTHORIZE_ERROR]
    ];

    protected $failMessages = [
        ['Type' => AuthorizeService::MESSAGE_AUTHORIZE_ERROR]
    ];

    protected $offsiteSuccessMessages = [
        ['Type' => AuthorizeService::MESSAGE_AUTHORIZE_REQUEST],
        ['Type' => AuthorizeService::MESSAGE_AUTHORIZE_REDIRECT_RESPONSE],
        ['Type' => AuthorizeService::MESSAGE_COMPLETE_AUTHORIZE_REQUEST],
        ['Type' => AuthorizeService::MESSAGE_AUTHORIZED_RESPONSE]
    ];

    protected $offsiteFailMessages = [
        ['Type' => AuthorizeService::MESSAGE_AUTHORIZE_REQUEST],
        ['Type' => AuthorizeService::MESSAGE_AUTHORIZE_REDIRECT_RESPONSE],
        ['Type' => AuthorizeService::MESSAGE_COMPLETE_AUTHORIZE_REQUEST],
        ['Type' => AuthorizeService::MESSAGE_COMPLETE_AUTHORIZE_ERROR]
    ];

    protected $failureMessageType = AuthorizeService::MESSAGE_COMPLETE_AUTHORIZE_ERROR;

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

    public function setUp(): void
    {
        parent::setUp();

        AuthorizeService::add_extension(PaymentTestServiceExtensionHooks::class);

        Config::modify()->merge(GatewayInfo::class, 'PaymentExpress_PxPay', [
            'use_authorize' => true
        ]);
    }

    public function tearDown(): void
    {
        parent::tearDown();

        AuthorizeService::remove_extension(PaymentTestServiceExtensionHooks::class);
    }

    protected function getService(Payment $payment)
    {
        return AuthorizeService::create($payment);
    }
}
