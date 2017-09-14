<?php

namespace SilverStripe\Omnipay\Tests;

use SilverStripe\Omnipay\Service\CreateCardService;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Core\Injector\Injector;

class CreateCardServiceTest extends BasePurchaseServiceTest
{
    protected $completeStatus = 'CardCreated';
    protected $pendingStatus = 'PendingCreateCard';

    protected $omnipayMethod = 'createCard';
    protected $omnipayCompleteMethod = 'completeCreateCard';

    protected $onsiteSuccessMessages = array(
        array('ClassName' => 'CreateCardRequest'),
        array('ClassName' => 'CreateCardResponse')
    );

    protected $onsiteFailMessages = array(
        array('ClassName' => 'CreateCardRequest'),
        array('ClassName' => 'CreateCardError')
    );

    protected $failMessages = array(
        array('ClassName' => 'CreateCardError')
    );

    protected $offsiteSuccessMessages = array(
        array('ClassName' => 'CreateCardRequest'),
        array('ClassName' => 'CreateCardRedirectResponse'),
        array('ClassName' => 'CompleteCreateCardRequest'),
        array('ClassName' => 'CreateCardResponse')
    );

    protected $offsiteFailMessages = array(
        array('ClassName' => 'CreateCardResponse'),
        array('ClassName' => 'CompleteCreateCardRequest'),
        array('ClassName' => 'CompleteCreateCardError')
    );

    protected $failureMessageClass = 'CompleteCreateCardError';

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
        CreateCardService::add_extension('PaymentTest_ServiceExtensionHooks');
    }

    public function tearDown()
    {
        parent::tearDown();
        CreateCardService::remove_extension('PaymentTest_ServiceExtensionHooks');
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
