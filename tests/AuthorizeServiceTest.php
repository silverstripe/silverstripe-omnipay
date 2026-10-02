<?php

declare(strict_types=1);

namespace SilverStripe\Omnipay\Tests;

use Omnipay\Common\GatewayFactory;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Service\AuthorizeService;
use SilverStripe\Omnipay\Service\PaymentService;
use SilverStripe\Omnipay\Service\ServiceFactory;
use SilverStripe\Omnipay\Tests\Extensions\PaymentTestPaymentExtensionHooks;
use SilverStripe\Omnipay\Tests\Extensions\PaymentTestServiceExtensionHooks;
use SilverStripe\Omnipay\Tests\Service\TestGatewayFactory;

class AuthorizeServiceTest extends FunctionalTest
{
    use BasePurchaseServiceTestTrait;
    use PaymentTestTrait;

    protected static $fixture_file = 'PaymentTest.yml';

    protected $autoFollowRedirection = false;

    protected string $completeStatus = 'Authorized';

    protected string $pendingStatus = 'PendingAuthorization';

    protected string $omnipayMethod = 'authorize';

    protected string $omnipayCompleteMethod = 'completeAuthorize';

    protected array $onsiteSuccessMessages = [
        ['Type' => AuthorizeService::MESSAGE_AUTHORIZE_REQUEST],
        ['Type' => AuthorizeService::MESSAGE_AUTHORIZED_RESPONSE]
    ];

    protected array $onsiteFailMessages = [
        ['Type' => AuthorizeService::MESSAGE_AUTHORIZE_REQUEST],
        ['Type' => AuthorizeService::MESSAGE_AUTHORIZE_ERROR]
    ];

    protected array $failMessages = [
        ['Type' => AuthorizeService::MESSAGE_AUTHORIZE_ERROR]
    ];

    protected array $offsiteSuccessMessages = [
        ['Type' => AuthorizeService::MESSAGE_AUTHORIZE_REQUEST],
        ['Type' => AuthorizeService::MESSAGE_AUTHORIZE_REDIRECT_RESPONSE],
        ['Type' => AuthorizeService::MESSAGE_COMPLETE_AUTHORIZE_REQUEST],
        ['Type' => AuthorizeService::MESSAGE_AUTHORIZED_RESPONSE]
    ];

    protected array $offsiteFailMessages = [
        ['Type' => AuthorizeService::MESSAGE_AUTHORIZE_REQUEST],
        ['Type' => AuthorizeService::MESSAGE_AUTHORIZE_REDIRECT_RESPONSE],
        ['Type' => AuthorizeService::MESSAGE_COMPLETE_AUTHORIZE_REQUEST],
        ['Type' => AuthorizeService::MESSAGE_COMPLETE_AUTHORIZE_ERROR]
    ];

    protected string $failureMessageType = AuthorizeService::MESSAGE_COMPLETE_AUTHORIZE_ERROR;

    protected string $paymentId = '62b26e0a8a77f60cce3e9a7994087b0e';

    protected array $successPaymentExtensionHooks = [
        'onAuthorized'
    ];

    protected array $notifyPaymentExtensionHooks = [
        'onAwaitingAuthorized'
    ];

    protected array $initiateServiceExtensionHooks = [
        'onBeforeAuthorize',
        'onAfterAuthorize',
        'onAfterSendAuthorize',
        'updateServiceResponse'
    ];

    protected array $initiateFailedServiceExtensionHooks = [
        'onBeforeAuthorize',
        'onAfterAuthorize',
        'updateServiceResponse'
    ];

    protected array $completeServiceExtensionHooks = [
        'onBeforeCompleteAuthorize',
        'onAfterCompleteAuthorize',
        'updateServiceResponse'
    ];

    protected function setUp(): void
    {
        parent::setUp();

        PaymentTestPaymentExtensionHooks::ResetAll();

        $this->factory = ServiceFactory::create();

        Payment::config()->set('allowed_gateways', [
            'PayPal_Express',
            'PaymentExpress_PxPay',
            'Manual',
            'Dummy'
        ]);

        // clear settings for PaymentExpress_PxPay (don't let user configs bleed into tests)
        Config::modify()
            ->remove(GatewayInfo::class, 'PaymentExpress_PxPay')
            ->set(GatewayInfo::class, 'PaymentExpress_PxPay', [
                'parameters' => [
                    'username' => 'EXAMPLEUSER',
                    'password' => '235llgwxle4tol23l'
                ]
            ]);

        //set up a payment here to make tests shorter
        $this->payment = Payment::create()
            ->setGateway("Dummy")
            ->setAmount(1222)
            ->setCurrency("GBP");

        Config::modify()->set(Injector::class, GatewayFactory::class, [
            'class' => TestGatewayFactory::class
        ]);

        TestGatewayFactory::$httpClient = $this->getHttpClient();
        TestGatewayFactory::$httpRequest = $this->getHttpRequest();

        AuthorizeService::add_extension(PaymentTestServiceExtensionHooks::class);

        Config::modify()->merge(GatewayInfo::class, 'PaymentExpress_PxPay', [
            'use_authorize' => true
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        AuthorizeService::remove_extension(PaymentTestServiceExtensionHooks::class);
    }

    protected function getService(Payment $payment): PaymentService
    {
        return AuthorizeService::create($payment);
    }
}
