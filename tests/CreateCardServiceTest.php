<?php

declare(strict_types=1);

namespace SilverStripe\Omnipay\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use Omnipay\Common\GatewayFactory;
use Omnipay\Dummy\Message\CreditCardRequest;
use Omnipay\Dummy\Gateway;
use Omnipay\Common\Http\ClientInterface;
use Omnipay\Dummy\Message\Response;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Service\CreateCardService;
use SilverStripe\Omnipay\Service\PaymentService;
use SilverStripe\Omnipay\Service\ServiceFactory;
use SilverStripe\Omnipay\Tests\Extensions\PaymentTestPaymentExtensionHooks;
use SilverStripe\Omnipay\Tests\Extensions\PaymentTestServiceExtensionHooks;
use SilverStripe\Omnipay\Tests\Service\TestGatewayFactory;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class CreateCardServiceTest extends FunctionalTest
{
    use BasePurchaseServiceTestTrait;
    use PaymentTestTrait;

    protected static $fixture_file = 'PaymentTest.yml';

    protected $autoFollowRedirection = false;

    protected string $completeStatus = 'CardCreated';

    protected string $pendingStatus = 'PendingCreateCard';

    protected string $omnipayMethod = 'createCard';

    protected string $omnipayCompleteMethod = 'completeCreateCard';

    protected array $onsiteSuccessMessages = [
        ['Type' => CreateCardService::MESSAGE_CREATE_CARD_REQUEST],
        ['Type' => CreateCardService::MESSAGE_CREATE_CARD_RESPONSE]
    ];

    protected array $onsiteFailMessages = [
        ['Type' => CreateCardService::MESSAGE_CREATE_CARD_REQUEST],
        ['Type' => CreateCardService::MESSAGE_CREATE_CARD_ERROR]
    ];

    protected array $failMessages = [
        ['Type' => CreateCardService::MESSAGE_CREATE_CARD_ERROR]
    ];

    protected array $offsiteSuccessMessages = [
        ['Type' => CreateCardService::MESSAGE_CREATE_CARD_REQUEST],
        ['Type' => CreateCardService::MESSAGE_CREATE_CARD_REDIRECT_RESPONSE],
        ['Type' => CreateCardService::MESSAGE_COMPLETE_CREATE_CARD_REQUEST],
        ['Type' => CreateCardService::MESSAGE_CREATE_CARD_RESPONSE]
    ];

    protected array $offsiteFailMessages = [
        ['Type' => CreateCardService::MESSAGE_CREATE_CARD_RESPONSE],
        ['Type' => CreateCardService::MESSAGE_COMPLETE_CREATE_CARD_REQUEST],
        ['Type' => CreateCardService::MESSAGE_COMPLETE_CREATE_CARD_ERROR]
    ];

    protected string $failureMessageType = CreateCardService::MESSAGE_COMPLETE_CREATE_CARD_ERROR;

    protected string $paymentId = '18f2fcac2b8f7549fd0295b251d9e9db';

    protected array $successPaymentExtensionHooks = [
        'onCardCreated'
    ];

    protected array $notifyPaymentExtensionHooks = [
        'onAwaitingCreateCard'
    ];

    protected array $initiateServiceExtensionHooks = [
        'onBeforeCreateCard',
        'onAfterCreateCard',
        'onAfterSendCreateCard',
        'updateServiceResponse'
    ];

    protected array $initiateFailedServiceExtensionHooks = [
        'onBeforeCreateCard',
        'onAfterCreateCard',
        'updateServiceResponse'
    ];

    protected array $completeServiceExtensionHooks = [
        'onBeforeCompleteCreateCard',
        'onAfterCompleteCreateCard',
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

        CreateCardService::add_extension(PaymentTestServiceExtensionHooks::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        CreateCardService::remove_extension(PaymentTestServiceExtensionHooks::class);
    }

    protected function getService(Payment $payment): PaymentService
    {
        return CreateCardService::create($payment);
    }

    protected function buildDummyGatewayMock($successValue): MockObject
    {
        //--------------------------------------------------------------------------------------------------------------
        // Payment request and response

        $mockPaymentResponse = $this->createMock(Response::class);

        $mockPaymentResponse
            ->method('isSuccessful')
            ->willReturn($successValue);

        $mockPaymentRequest = $this
            ->getMockBuilder(CreditCardRequest::class)
            ->setConstructorArgs([
                $this->createStub(ClientInterface::class),
                new SymfonyRequest(),
            ])
            ->onlyMethods(['send'])
            ->getMock();

        $mockPaymentRequest
            ->method('send')
            ->willReturn($mockPaymentResponse);

        //--------------------------------------------------------------------------------------------------------------
        // Build the gateway

        $stubGateway = $this
            ->getMockBuilder(Gateway::class)
            ->onlyMethods(['createCard', 'getName'])
            ->getMock();

        $stubGateway->expects($this->once())
            ->method('createCard')
            ->willReturn($mockPaymentRequest);

        return $stubGateway;
    }
}
