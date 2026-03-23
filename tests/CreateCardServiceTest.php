<?php

namespace SilverStripe\Omnipay\Tests;

use Omnipay\Common\Http\ClientInterface;
use SilverStripe\Omnipay\Service\CreateCardService;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Omnipay\Tests\Extensions\PaymentTestServiceExtensionHooks;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class CreateCardServiceTest extends BasePurchaseServiceTest
{
    protected $completeStatus = 'CardCreated';
    protected $pendingStatus = 'PendingCreateCard';

    protected $omnipayMethod = 'createCard';
    protected $omnipayCompleteMethod = 'completeCreateCard';

    protected $onsiteSuccessMessages = [
        ['Type' => CreateCardService::MESSAGE_CREATE_CARD_REQUEST],
        ['Type' => CreateCardService::MESSAGE_CREATE_CARD_RESPONSE]
    ];

    protected $onsiteFailMessages = [
        ['Type' => CreateCardService::MESSAGE_CREATE_CARD_REQUEST],
        ['Type' => CreateCardService::MESSAGE_CREATE_CARD_ERROR]
    ];

    protected $failMessages = [
        ['Type' => CreateCardService::MESSAGE_CREATE_CARD_ERROR]
    ];

    protected $offsiteSuccessMessages = [
        ['Type' => CreateCardService::MESSAGE_CREATE_CARD_REQUEST],
        ['Type' => CreateCardService::MESSAGE_CREATE_CARD_REDIRECT_RESPONSE],
        ['Type' => CreateCardService::MESSAGE_COMPLETE_CREATE_CARD_REQUEST],
        ['Type' => CreateCardService::MESSAGE_CREATE_CARD_RESPONSE]
    ];

    protected $offsiteFailMessages = [
        ['Type' => CreateCardService::MESSAGE_CREATE_CARD_RESPONSE],
        ['Type' => CreateCardService::MESSAGE_COMPLETE_CREATE_CARD_REQUEST],
        ['Type' => CreateCardService::MESSAGE_COMPLETE_CREATE_CARD_ERROR]
    ];

    protected $failureMessageType = CreateCardService::MESSAGE_COMPLETE_CREATE_CARD_ERROR;

    protected $paymentId = '18f2fcac2b8f7549fd0295b251d9e9db';

    protected $successPaymentExtensionHooks = [
        'onCardCreated'
    ];

    protected $notifyPaymentExtensionHooks = [
        'onAwaitingCreateCard'
    ];

    protected $initiateServiceExtensionHooks = [
        'onBeforeCreateCard',
        'onAfterCreateCard',
        'onAfterSendCreateCard',
        'updateServiceResponse'
    ];

    protected $initiateFailedServiceExtensionHooks = [
        'onBeforeCreateCard',
        'onAfterCreateCard',
        'updateServiceResponse'
    ];

    protected $completeServiceExtensionHooks = [
        'onBeforeCompleteCreateCard',
        'onAfterCompleteCreateCard',
        'updateServiceResponse'
    ];

    public function setUp(): void
    {
        parent::setUp();
        CreateCardService::add_extension(PaymentTestServiceExtensionHooks::class);
    }

    public function tearDown(): void
    {
        parent::tearDown();
        CreateCardService::remove_extension(PaymentTestServiceExtensionHooks::class);
    }

    protected function getService(Payment $payment)
    {
        return CreateCardService::create($payment);
    }

    public function testDummyOnSitePayment()
    {
        $stubGateway = $this->buildDummyGatewayMock(true);
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), 'Omnipay\Common\GatewayFactory');

        parent::testDummyOnSitePayment();
    }

    public function testFailedDummyOnSitePayment()
    {
        $stubGateway = $this->buildDummyGatewayMock(false);
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), 'Omnipay\Common\GatewayFactory');

        parent::testFailedDummyOnSitePayment();
    }

    protected function buildDummyGatewayMock($successValue)
    {
        //--------------------------------------------------------------------------------------------------------------
        // Payment request and response

        $mockPaymentResponse = $this
            ->getMockBuilder('Omnipay\Dummy\Message\Response')
            ->disableOriginalConstructor()
            ->onlyMethods(['isSuccessful'])
            ->getMock();

        $mockPaymentResponse
            ->expects($this->any())
            ->method('isSuccessful')
            ->will($this->returnValue($successValue));

        $mockPaymentRequest = $this
            ->getMockBuilder('Omnipay\Dummy\Message\CreditCardRequest')
            ->setConstructorArgs([
                $this->createMock(ClientInterface::class),
                $this->createMock(SymfonyRequest::class),
            ])
            ->onlyMethods(['send'])
            ->getMock();

        $mockPaymentRequest
            ->expects($this->any())
            ->method('send')
            ->will($this->returnValue($mockPaymentResponse));

        //--------------------------------------------------------------------------------------------------------------
        // Build the gateway

        $stubGateway = $this
            ->getMockBuilder('Omnipay\Dummy\Gateway')
            ->onlyMethods(['createCard', 'getName'])
            ->getMock();

        $stubGateway->expects($this->once())
            ->method('createCard')
            ->will($this->returnValue($mockPaymentRequest));

        return $stubGateway;
    }
}
