<?php

namespace SilverStripe\Omnipay\Tests;

use SilverStripe\Omnipay\Exception\InvalidConfigurationException;
use SilverStripe\Omnipay\Service\RefundService;
use Omnipay\Common\Message\NotificationInterface;
use SilverStripe\Omnipay\Tests\Extensions\PaymentTestServiceExtensionHooks;
use SilverStripe\Omnipay\Tests\Extensions\PaymentTestPaymentExtensionHooks;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Model\Message;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Config\Config;
use SilverStripe\Omnipay\GatewayInfo;

/**
 * Test the refund service
 */
class RefundServiceTest extends BaseNotificationServiceTest
{
    protected $gatewayMethod = 'refund';

    protected $fixtureIdentifier = 'payment3';

    protected $fixtureReceipt = 'paymentReceipt';

    protected $startStatus = 'Captured';

    protected $pendingStatus = 'PendingRefund';

    protected $endStatus = 'Refunded';

    protected $successFromFixtureMessages = array(
        array( // response that was loaded from the fixture
            'ClassName' => Message\PurchasedResponse::class,
            'Reference' => 'paymentReceipt'
        ),
        array( // the generated refund request
            'ClassName' => Message\RefundRequest::class,
            'Reference' => 'paymentReceipt'
        ),
        array( // the generated refund response
            'ClassName' => Message\RefundedResponse::class,
            'Reference' => 'paymentReceipt'
        )
    );

    protected $successMessages = array(
        array( // the generated refund request
            'ClassName' => Message\RefundRequest::class,
            'Reference' => 'testThisRecipe123'
        ),
        array( // the generated refund response
            'ClassName' => Message\RefundedResponse::class,
            'Reference' => 'testThisRecipe123'
        )
    );

    protected $failureMessages = array(
        array( // response that was loaded from the fixture
            'ClassName' => Message\PurchasedResponse::class,
            'Reference' => 'paymentReceipt'
        ),
        array( // the generated refund request
            'ClassName' => Message\RefundRequest::class,
            'Reference' => 'paymentReceipt'
        ),
        array( // the generated refund response
            'ClassName' => Message\RefundError::class,
            'Reference' => 'paymentReceipt'
        )
    );

    protected $notificationFailureMessages = array(
        array(
            'ClassName' => Message\PurchasedResponse::class,
            'Reference' => 'paymentReceipt'
        ),
        array(
            'ClassName' => Message\RefundRequest::class,
            'Reference' => 'paymentReceipt'
        ),
        array(
            'ClassName' => Message\NotificationError::class,
            'Reference' => 'paymentReceipt'
        )
    );

    protected $errorMessageClass = Message\RefundError::class;

    protected $successPaymentExtensionHooks = array(
        'onRefunded'
    );

    protected $initiateServiceExtensionHooks = array(
        'onBeforeRefund',
        'onAfterRefund',
        'onAfterSendRefund',
        'updateServiceResponse'
    );

    protected $initiateFailedServiceExtensionHooks = array(
        'onBeforeRefund',
        'onAfterRefund',
        'updateServiceResponse'
    );

    public function setUp()
    {
        parent::setUp();
        $this->logInWithPermission('REFUND_PAYMENTS');
        RefundService::add_extension(PaymentTestServiceExtensionHooks::class);
    }

    public function tearDown()
    {
        parent::tearDown();
        RefundService::remove_extension(PaymentTestServiceExtensionHooks::class);
    }

    protected function getService(Payment $payment)
    {
        return RefundService::create($payment);
    }

    public function testFullRefund()
    {
        // load a captured payment from fixture
        $payment = $this->objFromFixture(Payment::class, $this->fixtureIdentifier);

        $stubGateway = $this->buildPaymentGatewayStub(true, $this->fixtureReceipt);
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), 'Omnipay\Common\GatewayFactory');

        $service = $this->getService($payment);

        // We supply the amount, but specify the full amount here. So this should be equal to a full refund
        $service->initiate(array('amount' => '769.50'));

        // there should be NO partial payments
        $this->assertEquals(0, $payment->getPartialPayments()->count());

        // check payment status
        $this->assertEquals($payment->Status, $this->endStatus, 'Payment status should be set to ' . $this->endStatus);
        $this->assertEquals('769.50', $payment->MoneyAmount);

        // check existance of messages and existence of references
        $this->assertDOSContains($this->successFromFixtureMessages, $payment->Messages());

        // ensure payment hooks were called
        $this->assertEquals(
            $this->successPaymentExtensionHooks,
            $payment->getExtensionInstance(PaymentTestPaymentExtensionHooks::class)->getCalledMethods()
        );

        // ensure the correct service hooks were called
        $this->assertEquals(
            $this->initiateServiceExtensionHooks,
            $service->getExtensionInstance(PaymentTestServiceExtensionHooks::class)->getCalledMethods()
        );
    }

    public function testPartialRefund()
    {
        // load a captured payment from fixture
        $payment = $this->objFromFixture(Payment::class, $this->fixtureIdentifier);

        $stubGateway = $this->buildPaymentGatewayStub(true, $this->fixtureReceipt);
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), 'Omnipay\Common\GatewayFactory');

        $service = $this->getService($payment);

        // We do a partial refund
        $service->initiate(array('amount' => '100.50'));

        // there should be a new partial payment
        $this->assertEquals(1, $payment->getPartialPayments()->count());

        $partialPayment = $payment->getPartialPayments()->first();
        $this->assertEquals('Refunded', $partialPayment->Status);
        $this->assertEquals('100.50', $partialPayment->MoneyAmount);

        // check payment status. It should still be captured, as it's not fully refunded
        $this->assertEquals('Captured', $payment->Status);
        // the original payment should now have less balance
        $this->assertEquals('669.00', $payment->MoneyAmount);
        // payment can no longer be refunded (as multiple refunds are disabled by default)
        $this->assertFalse($payment->canRefund(null, true));

        // check existance of messages and existence of references
        $this->assertDOSContains(array(
            array(
                'ClassName' => Message\PurchasedResponse::class,
                'Reference' => 'paymentReceipt',
            ),

            array(
                'ClassName' => Message\RefundRequest::class,
                'Reference' => 'paymentReceipt',
            ),
            array(
                'ClassName' => Message\PartiallyRefundedResponse::class,
                'Reference' => 'paymentReceipt',
            ),
        ), $payment->Messages());

        // ensure payment hooks were called
        $this->assertEquals(
            $this->successPaymentExtensionHooks,
            $payment->getExtensionInstance(PaymentTestPaymentExtensionHooks::class)->getCalledMethods()
        );

        // ensure the correct service hooks were called
        $this->assertEquals(
            array_merge($this->initiateServiceExtensionHooks, array('updatePartialPayment')),
            $service->getExtensionInstance(PaymentTestServiceExtensionHooks::class)->getCalledMethods()
        );
    }

    public function testMultiplePartialRefunds()
    {
        // load a captured payment from fixture
        $payment = $this->objFromFixture(Payment::class, $this->fixtureIdentifier);

        // allow multiple partial captures
        Config::modify()->merge(GatewayInfo::class, $payment->Gateway, array(
            'can_refund' => 'multiple'
        ));

        $stubGateway = $this->buildPaymentGatewayStub(true, $this->fixtureReceipt);
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), 'Omnipay\Common\GatewayFactory');

        $service = $this->getService($payment);

        // We do a partial refund
        $service->initiate(array('amount' => '100.50'));

        // there should be a new partial payment
        $this->assertEquals(1, $payment->getPartialPayments()->count());

        $partialPayment = $payment->getPartialPayments()->first();
        $this->assertEquals('Refunded', $partialPayment->Status);
        $this->assertEquals('100.50', $partialPayment->MoneyAmount);

        // check payment status. It should still be captured, as it's not fully refunded
        $this->assertEquals('Captured', $payment->Status);
        // the original payment should now have less balance
        $this->assertEquals('669.00', $payment->MoneyAmount);
        // payment can still be refunded (as multiple refunds were enabled)
        $this->assertTrue($payment->canRefund(null, true));

        // refund some more
        $service->initiate(array('amount' => '569'));

        $partialPayment = $payment->getPartialPayments()->first();
        $this->assertEquals('Refunded', $partialPayment->Status);
        $this->assertEquals('569.00', $partialPayment->MoneyAmount);

        $this->assertEquals('Captured', $payment->Status);
        $this->assertEquals('100.00', $payment->MoneyAmount);
        $this->assertTrue($payment->canRefund(null, true));

        // refund the rest
        $service->initiate(array('amount' => '100.00'));
        $this->assertEquals('Refunded', $payment->Status);
        $this->assertEquals('100.00', $payment->MoneyAmount);
        $this->assertFalse($payment->canRefund(null, true));
    }

    public function testPartialRefundViaNotification()
    {
        // load a payment from fixture
        $payment = $this->objFromFixture(Payment::class, $this->fixtureIdentifier);

        // use notification on the gateway
        Config::modify()->merge(GatewayInfo::class, $payment->Gateway, array(
            'use_async_notification' => true
        ));

        $stubGateway = $this->buildPaymentGatewayStub(false, $this->fixtureReceipt);
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), 'Omnipay\Common\GatewayFactory');

        $service = $this->getService($payment);

        $service->initiate(array('amount' => '669.50'));

        // payment amount should still be the full amount!
        $this->assertEquals('769.50', $payment->MoneyAmount);

        // there must be a partial payment
        $this->assertEquals(1, $payment->getPartialPayments()->count());

        // the partial payment should be pending and negative
        $partialPayment = $payment->getPartialPayments()->first();
        $this->assertEquals('PendingRefund', $partialPayment->Status);
        $this->assertEquals('-669.50', $partialPayment->MoneyAmount);

        // Now a notification comes in
        $this->get('paymentendpoint/'. $payment->Identifier .'/notify');

        // ensure payment hooks were called
        $this->assertEquals(
            $this->successPaymentExtensionHooks,
            PaymentTestPaymentExtensionHooks::findExtensionForID($payment->ID)->getCalledMethods()
        );

        // ensure the correct service hooks were called
        $this->assertEquals(
            array_merge($this->initiateServiceExtensionHooks, array('updatePartialPayment')),
            $service->getExtensionInstance(PaymentTestServiceExtensionHooks::class)->getCalledMethods()
        );

        // we'll have to "reload" the payment from the DB now
        $payment = Payment::get()->byID($payment->ID);

        // Status should still be captured
        $this->assertEquals('Captured', $payment->Status);
        // the payment balance is reduced to 100.00
        $this->assertEquals('100.00', $payment->MoneyAmount);

        // the partial payment should no longer be pending and positive
        $partialPayment = $payment->getPartialPayments()->first();
        $this->assertEquals('Refunded', $partialPayment->Status);
        $this->assertEquals('669.50', $partialPayment->MoneyAmount);

        // check existance of messages
        $this->assertDOSContains(array(
            array(
                'ClassName' => Message\PurchasedResponse::class,
                'Reference' => 'paymentReceipt'
            ),
            array(
                'ClassName' => Message\RefundRequest::class,
                'Reference' => 'paymentReceipt'
            ),
            array(
                'ClassName' => Message\NotificationSuccessful::class,
                'Reference' => 'paymentReceipt'
            ),
            array(
                'ClassName' => Message\PartiallyRefundedResponse::class,
                'Reference' => 'paymentReceipt'
            )
        ), $payment->Messages());

        // try to complete a second time
        $service = $this->getService($payment);
        $serviceResponse = $service->complete();

        // the service should respond with an error, since the payment is not (fully) refunded
        $this->assertTrue($serviceResponse->isError());
        // since the payment is already completed, we should not touch omnipay again.
        $this->assertNull($serviceResponse->getOmnipayResponse());
    }

    public function testMultipleInitiateCallsBeforeNotificationArrives()
    {
        // load a payment from fixture
        $payment = $this->objFromFixture(Payment::class, $this->fixtureIdentifier);

        // use notification on the gateway
        Config::modify()->merge(GatewayInfo::class, $payment->Gateway, array(
            'use_async_notification' => true
        ));

        $stubGateway = $this->buildPaymentGatewayStub(false, $this->fixtureReceipt);
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), 'Omnipay\Common\GatewayFactory');

        $service = $this->getService($payment);

        // try to initiate two refunds without waiting for one to complete
        $service->initiate(array('amount' => '100.00'));

        $exception = null;
        try {
            // the second attempt must throw an exception!
            $service->initiate(array('amount' => '69.50'));
        } catch (\Exception $ex) {
            $exception = $ex;
        }

        $this->assertInstanceOf(InvalidConfigurationException::class, $exception);

        // there must be a partial payment
        $this->assertEquals(1, $payment->getPartialPayments()->count());

        // the partial payment should be pending and have the first initiated amount
        $partialPayment = $payment->getPartialPayments()->first();
        $this->assertEquals('PendingRefund', $partialPayment->Status);
        $this->assertEquals('-100.00', $partialPayment->MoneyAmount);

        // check existance of messages
        $this->assertDOSContains(array(
            array(
                'ClassName' => Message\PurchasedResponse::class,
                'Reference' => 'paymentReceipt'
            ),
            array(
                'ClassName' => Message\RefundRequest::class,
                'Reference' => 'paymentReceipt'
            )
        ), $payment->Messages());
    }

    /**
     * @expectedException \SilverStripe\Omnipay\Exception\InvalidParameterException
     */
    public function testLargerAmount()
    {
        $stubGateway = $this->buildPaymentGatewayStub(true, $this->fixtureReceipt);
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), 'Omnipay\Common\GatewayFactory');

        // load a captured payment from fixture
        $payment = $this->objFromFixture(Payment::class, $this->fixtureIdentifier);
        $service = $this->getService($payment);

        // We supply the amount, but specify an amount that is way over what was captured
        // This will throw an InvalidParameterException
        $service->initiate(array('amount' => '1000000.00'));
    }

    /**
     * @expectedException \SilverStripe\Omnipay\Exception\InvalidParameterException
     */
    public function testInvalidAmount()
    {
        $stubGateway = $this->buildPaymentGatewayStub(true, $this->fixtureReceipt);
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), 'Omnipay\Common\GatewayFactory');

        // load a captured payment from fixture
        $payment = $this->objFromFixture(Payment::class, $this->fixtureIdentifier);
        $service = $this->getService($payment);

        // We supply the amount, but specify an amount that is not a number
        // This will throw an InvalidParameterException
        $service->initiate(array('amount' => 'test'));
    }

    /**
     * @expectedException \SilverStripe\Omnipay\Exception\InvalidParameterException
     */
    public function testNegativeAmount()
    {
        $stubGateway = $this->buildPaymentGatewayStub(true, $this->fixtureReceipt);
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), 'Omnipay\Common\GatewayFactory');

        // load a captured payment from fixture
        $payment = $this->objFromFixture(Payment::class, $this->fixtureIdentifier);
        $service = $this->getService($payment);

        // We supply the amount, but specify an amount that is not a positive number
        // This will throw an InvalidParameterException
        $service->initiate(array('amount' => '-100'));
    }

    /**
     * @expectedException \SilverStripe\Omnipay\Exception\InvalidParameterException
     */
    public function testPartialRefundUnsupported()
    {
        $stubGateway = $this->buildPaymentGatewayStub(true, $this->fixtureReceipt);
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), 'Omnipay\Common\GatewayFactory');

        // load a captured payment from fixture
        $payment = $this->objFromFixture(Payment::class, $this->fixtureIdentifier);
        $service = $this->getService($payment);

        // only allow full refunds, thus disabling partial refunds
        Config::modify()->merge(GatewayInfo::class, $payment->Gateway, array(
           'can_refund' => 'full'
        ));

        // We supply a partial amount
        // This will throw an InvalidParameterException
        $service->initiate(array('amount' => '10.00'));
    }

    public function testPartialRefundFailed()
    {
        $stubGateway = $this->buildPaymentGatewayStub(false, $this->fixtureReceipt);
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), 'Omnipay\Common\GatewayFactory');

        // load a captured payment from fixture
        $payment = $this->objFromFixture(Payment::class, $this->fixtureIdentifier);
        $service = $this->getService($payment);

        $service->initiate(array('amount' => '100.00'));

        // there should be NO partial payments
        $this->assertEquals(0, $payment->getPartialPayments()->count());

        // Payment should be unaltered
        $this->assertEquals('Captured', $payment->Status);
        $this->assertEquals('769.50', $payment->MoneyAmount);
    }

    public function testPartialRefundViaNotificationFailed()
    {
        // load a payment from fixture
        $payment = $this->objFromFixture(Payment::class, $this->fixtureIdentifier);

        // use notification on the gateway
        Config::modify()->merge(GatewayInfo::class, $payment->Gateway, array(
            'use_async_notification' => true
        ));

        $stubGateway = $this->buildPaymentGatewayStub(
            false,
            $this->fixtureReceipt,
            NotificationInterface::STATUS_FAILED
        );

        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), 'Omnipay\Common\GatewayFactory');

        $service = $this->getService($payment);

        $service->initiate(array('amount' => '669.50'));

        // Now a notification comes in (will fail)
        $this->get('paymentendpoint/'. $payment->Identifier .'/notify');

        // we'll have to "reload" the payment from the DB now
        $payment = Payment::get()->byID($payment->ID);

        // Status should be reset
        $this->assertEquals('Captured', $payment->Status);
        // the payment balance is unaltered
        $this->assertEquals('769.50', $payment->MoneyAmount);

        // the partial payment should be void
        $partialPayment = $payment->getPartialPayments()->first();
        $this->assertEquals('Void', $partialPayment->Status);
        $this->assertEquals('-669.50', $partialPayment->MoneyAmount);

        // check existance of messages
        $this->assertDOSContains($this->notificationFailureMessages, $payment->Messages());
    }
}
