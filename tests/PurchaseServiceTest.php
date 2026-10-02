<?php

declare(strict_types=1);

namespace SilverStripe\Omnipay\Tests;

use Omnipay\Common\GatewayFactory;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Service\PaymentService;
use SilverStripe\Omnipay\Service\PurchaseService;
use SilverStripe\Omnipay\Service\ServiceFactory;
use SilverStripe\Omnipay\Tests\Extensions\PaymentTestPaymentExtensionHooks;
use SilverStripe\Omnipay\Tests\Extensions\PaymentTestServiceExtensionHooks;
use SilverStripe\Omnipay\Tests\Service\TestGatewayFactory;

class PurchaseServiceTest extends FunctionalTest
{
    use BasePurchaseServiceTestTrait;
    use PaymentTestTrait;

    protected static $fixture_file = 'PaymentTest.yml';

    protected $autoFollowRedirection = false;

    protected string $completeStatus = 'Captured';

    protected string $pendingStatus = 'PendingPurchase';

    protected string $omnipayMethod = 'purchase';

    protected string $omnipayCompleteMethod = 'completePurchase';

    protected array $onsiteSuccessMessages = [
        ['Type' => PurchaseService::MESSAGE_PURCHASE_REQUEST],
        ['Type' => PurchaseService::MESSAGE_PURCHASED_RESPONSE]
    ];

    protected array $onsiteFailMessages = [
        ['Type' => PurchaseService::MESSAGE_PURCHASE_REQUEST],
        ['Type' => PurchaseService::MESSAGE_PURCHASE_ERROR]
    ];

    protected array $failMessages = [
        ['Type' => PurchaseService::MESSAGE_PURCHASE_ERROR]
    ];

    protected array $offsiteSuccessMessages = [
        ['Type' => PurchaseService::MESSAGE_PURCHASE_REQUEST],
        ['Type' => PurchaseService::MESSAGE_PURCHASE_REDIRECT_RESPONSE],
        ['Type' => PurchaseService::MESSAGE_COMPLETE_PURCHASE_REQUEST],
        ['Type' => PurchaseService::MESSAGE_PURCHASED_RESPONSE]
    ];

    protected array $offsiteFailMessages = [
        ['Type' => PurchaseService::MESSAGE_PURCHASE_REQUEST],
        ['Type' => PurchaseService::MESSAGE_PURCHASE_REDIRECT_RESPONSE],
        ['Type' => PurchaseService::MESSAGE_COMPLETE_PURCHASE_REQUEST],
        ['Type' => PurchaseService::MESSAGE_COMPLETE_PURCHASE_ERROR]
    ];

    protected string $failureMessageType = PurchaseService::MESSAGE_COMPLETE_PURCHASE_ERROR;

    protected string $paymentId = 'UNIQUEHASH23q5123tqasdf';

    protected array $successPaymentExtensionHooks = [
        'onCaptured'
    ];

    protected array $notifyPaymentExtensionHooks = [
        'onAwaitingCaptured'
    ];

    protected array $initiateServiceExtensionHooks = [
        'onBeforePurchase',
        'onAfterPurchase',
        'onAfterSendPurchase',
        'updateServiceResponse'
    ];

    protected array $initiateFailedServiceExtensionHooks = [
        'onBeforePurchase',
        'onAfterPurchase',
        'updateServiceResponse'
    ];

    protected array $completeServiceExtensionHooks = [
        'onBeforeCompletePurchase',
        'onAfterCompletePurchase',
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

        PurchaseService::add_extension(PaymentTestServiceExtensionHooks::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        PurchaseService::remove_extension(PaymentTestServiceExtensionHooks::class);
    }

    protected function getService(Payment $payment): PaymentService
    {
        return PurchaseService::create($payment);
    }
}
