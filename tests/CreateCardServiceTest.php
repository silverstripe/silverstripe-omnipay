<?php

namespace SilverStripe\Omnipay\Tests;

use SilverStripe\Omnipay\Service\CreateCardService;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Omnipay\Tests\Extensions\PaymentTestServiceExtensionHooks;
use SilverStripe\Omnipay\Model\Message;

class CreateCardServiceTest extends BasePurchaseServiceTest
{
    protected $completeStatus = 'CardCreated';
    protected $pendingStatus = 'PendingCreateCard';

    protected $omnipayMethod = 'createCard';
    protected $omnipayCompleteMethod = 'completeCreateCard';

    protected $onsiteSuccessMessages = array(
        array('ClassName' => Message\CreateCardRequest::class),
        array('ClassName' => Message\CreateCardResponse::class)
    );

    protected $onsiteFailMessages = array(
        array('ClassName' => Message\CreateCardRequest::class),
        array('ClassName' => Message\CreateCardError::class)
    );

    protected $failMessages = array(
        array('ClassName' => Message\CreateCardError::class)
    );

    protected $offsiteSuccessMessages = array(
        array('ClassName' => Message\CreateCardRequest::class),
        array('ClassName' => Message\CreateCardRedirectResponse::class),
        array('ClassName' => Message\CompleteCreateCardRequest::class),
        array('ClassName' => Message\CreateCardResponse::class)
    );

    protected $offsiteFailMessages = array(
        array('ClassName' => Message\CreateCardResponse::class),
        array('ClassName' => Message\CompleteCreateCardRequest::class),
        array('ClassName' => Message\CompleteCreateCardError::class)
    );

    protected $failureMessageClass = Message\CompleteCreateCardError::class;

    protected $paymentId = '18f2fcac2b8f7549fd0295b251d9e9db';

    protected $successPaymentExtensionHooks = array(
        'onCardCreated'
    );

    protected $notifyPaymentExtensionHooks = array(
        'onAwaitingCreateCard'
    );

    protected $initiateServiceExtensionHooks = array(
        'onBeforeCreateCard',
        'onAfterCreateCard',
        'onAfterSendCreateCard',
        'updateServiceResponse'
    );

    protected $initiateFailedServiceExtensionHooks = array(
        'onBeforeCreateCard',
        'onAfterCreateCard',
        'updateServiceResponse'
    );

    protected $completeServiceExtensionHooks = array(
        'onBeforeCompleteCreateCard',
        'onAfterCompleteCreateCard',
        'updateServiceResponse'
    );

    public function setUp()
    {
        parent::setUp();
        CreateCardService::add_extension(PaymentTestServiceExtensionHooks::class);
    }

    public function tearDown()
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

        $mockPaymentResponse = $this->getMockBuilder('Omnipay\Dummy\Message\Response')
            ->disableOriginalConstructor()->getMock();

        $mockPaymentResponse->expects($this->any())
            ->method('isSuccessful')->will($this->returnValue($successValue));

        $mockPaymentRequest = $this->getMockBuilder('Omnipay\Dummy\Message\AuthorizeRequest')
            ->disableOriginalConstructor()->getMock();

        $mockPaymentRequest->expects($this->any())->method('send')->will($this->returnValue($mockPaymentResponse));

        //--------------------------------------------------------------------------------------------------------------
        // Build the gateway

        $stubGateway = $this->getMockBuilder('Omnipay\Common\AbstractGateway')
            ->setMethods(array('createCard', 'getName'))
            ->getMock();

        $stubGateway->expects($this->once())
            ->method('createCard')
            ->will($this->returnValue($mockPaymentRequest));

        return $stubGateway;
    }
}
