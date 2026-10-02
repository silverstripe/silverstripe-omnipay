<?php

declare(strict_types=1);

namespace SilverStripe\Omnipay\Tests;

use Omnipay\Common\GatewayFactory;
use SilverStripe\Omnipay\Exception\InvalidParameterException;
use Exception;
use Omnipay\Common\Message\NotificationInterface;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Omnipay\Exception\InvalidConfigurationException;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Service\PaymentService;
use SilverStripe\Omnipay\Service\PurchaseService;
use SilverStripe\Omnipay\Service\RefundService;
use SilverStripe\Omnipay\Tests\Extensions\PaymentTestPaymentExtensionHooks;
use SilverStripe\Omnipay\Tests\Extensions\PaymentTestServiceExtensionHooks;

/**
 * Test the refund service
 */
class RefundServiceTest extends FunctionalTest
{
    use BaseNotificationServiceTestTrait;
    use PaymentTestTrait;

    protected static $fixture_file = 'PaymentTest.yml';

    protected $autoFollowRedirection = false;

    protected string $gatewayMethod = 'refund';

    protected string $fixtureIdentifier = 'payment3';

    protected string $fixtureReceipt = 'paymentReceipt';

    protected string $startStatus = 'Captured';

    protected string $pendingStatus = 'PendingRefund';

    protected string $endStatus = 'Refunded';

    protected array $successFromFixtureMessages = [
        [ // response that was loaded from the fixture
            'Type' => PurchaseService::MESSAGE_PURCHASED_RESPONSE,
            'Reference' => 'paymentReceipt'
        ],
        [ // the generated refund request
            'Type' => RefundService::MESSAGE_REFUND_REQUEST,
            'Reference' => 'paymentReceipt'
        ],
        [ // the generated refund response
            'Type' => RefundService::MESSAGE_REFUNDED_RESPONSE,
            'Reference' => 'paymentReceipt'
        ]
    ];

    protected array $successMessages = [
        [ // the generated refund request
            'Type' => RefundService::MESSAGE_REFUND_REQUEST,
            'Reference' => 'testThisRecipe123'
        ],
        [ // the generated refund response
            'Type' => RefundService::MESSAGE_REFUNDED_RESPONSE,
            'Reference' => 'testThisRecipe123'
        ]
    ];

    protected array $failureMessages = [
        [ // response that was loaded from the fixture
            'Type' => PurchaseService::MESSAGE_PURCHASED_RESPONSE,
            'Reference' => 'paymentReceipt'
        ],
        [ // the generated refund request
            'Type' => RefundService::MESSAGE_REFUND_REQUEST,
            'Reference' => 'paymentReceipt'
        ],
        [ // the generated refund response
            'Type' => RefundService::MESSAGE_REFUND_ERROR,
            'Reference' => 'paymentReceipt'
        ]
    ];

    protected array $notificationFailureMessages = [
        [
            'Type' => PurchaseService::MESSAGE_PURCHASED_RESPONSE,
            'Reference' => 'paymentReceipt'
        ],
        [
            'Type' => RefundService::MESSAGE_REFUND_REQUEST,
            'Reference' => 'paymentReceipt'
        ],
        [
            'Type' => PaymentService::MESSAGE_NOTIFICATION_ERROR,
            'Reference' => 'paymentReceipt'
        ]
    ];

    protected string $errorMessageType = RefundService::MESSAGE_REFUND_ERROR;

    protected array $successPaymentExtensionHooks = [
        'onRefunded'
    ];

    protected array $initiateServiceExtensionHooks = [
        'onBeforeRefund',
        'onAfterRefund',
        'onAfterSendRefund',
        'updateServiceResponse'
    ];

    protected array $initiateFailedServiceExtensionHooks = [
        'onBeforeRefund',
        'onAfterRefund',
        'updateServiceResponse'
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->payment = Payment::create()
            ->setGateway("Dummy")
            ->setAmount(1222)
            ->setCurrency("GBP");
        $this->logInWithPermission('REFUND_PAYMENTS');
        RefundService::add_extension(PaymentTestServiceExtensionHooks::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        RefundService::remove_extension(PaymentTestServiceExtensionHooks::class);
    }

    protected function getService(Payment $payment): PaymentService
    {
        return RefundService::create($payment);
    }

    public function testFullRefund(): void
    {
        // load a captured payment from fixture
        $payment = $this->objFromFixture(Payment::class, $this->fixtureIdentifier);

        $stubGateway = $this->buildPaymentGatewayStub(true, $this->fixtureReceipt);
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), GatewayFactory::class);

        $paymentService = $this->getService($payment);

        // We supply the amount, but specify the full amount here. So this should be equal to a full refund
        $paymentService->initiate(['amount' => '769.50']);

        // there should be NO partial payments
        $this->assertEquals(0, $payment->getPartialPayments()->count());

        // check payment status
        $this->assertEquals($payment->Status, $this->endStatus, 'Payment status should be set to ' . $this->endStatus);
        $this->assertEquals('769.50', $payment->MoneyAmount);

        // check existance of messages and existence of references
        SapphireTest::assertListContains($this->successFromFixtureMessages, $payment->Messages());

        // ensure payment hooks were called
        $this->assertEquals(
            $this->successPaymentExtensionHooks,
            $payment->getExtensionInstance(PaymentTestPaymentExtensionHooks::class)->getCalledMethods()
        );

        // ensure the correct service hooks were called
        $this->assertEquals(
            $this->initiateServiceExtensionHooks,
            $paymentService->getExtensionInstance(PaymentTestServiceExtensionHooks::class)->getCalledMethods()
        );
    }

    public function testPartialRefund(): void
    {
        // load a captured payment from fixture
        $payment = $this->objFromFixture(Payment::class, $this->fixtureIdentifier);

        $stubGateway = $this->buildPaymentGatewayStub(true, $this->fixtureReceipt);
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), GatewayFactory::class);

        $paymentService = $this->getService($payment);

        // We do a partial refund
        $paymentService->initiate(['amount' => '100.50']);

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
        SapphireTest::assertListContains([
            [
                'Type' => PurchaseService::MESSAGE_PURCHASED_RESPONSE,
                'Reference' => 'paymentReceipt',
            ],

            [
                'Type' => RefundService::MESSAGE_REFUND_REQUEST,
                'Reference' => 'paymentReceipt',
            ],
            [
                'Type' => RefundService::MESSAGE_PARTIALLY_REFUNDED_RESPONSE,
                'Reference' => 'paymentReceipt',
            ],
        ], $payment->Messages());

        // ensure payment hooks were called
        $this->assertEquals(
            $this->successPaymentExtensionHooks,
            $payment->getExtensionInstance(PaymentTestPaymentExtensionHooks::class)->getCalledMethods()
        );

        // ensure the correct service hooks were called
        $this->assertEquals(
            array_merge($this->initiateServiceExtensionHooks, ['updatePartialPayment']),
            $paymentService->getExtensionInstance(PaymentTestServiceExtensionHooks::class)->getCalledMethods()
        );
    }

    public function testMultiplePartialRefunds(): void
    {
        // load a captured payment from fixture
        $payment = $this->objFromFixture(Payment::class, $this->fixtureIdentifier);

        // allow multiple partial captures
        Config::modify()->merge(GatewayInfo::class, $payment->Gateway, [
            'can_refund' => 'multiple'
        ]);

        $stubGateway = $this->buildPaymentGatewayStub(true, $this->fixtureReceipt);
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), GatewayFactory::class);

        $paymentService = $this->getService($payment);

        // We do a partial refund
        $paymentService->initiate(['amount' => '100.50']);

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
        $paymentService->initiate(['amount' => '569']);

        $partialPayment = $payment->getPartialPayments()->first();
        $this->assertEquals('Refunded', $partialPayment->Status);
        $this->assertEquals('569.00', $partialPayment->MoneyAmount);

        $this->assertEquals('Captured', $payment->Status);
        $this->assertEquals('100.00', $payment->MoneyAmount);
        $this->assertTrue($payment->canRefund(null, true));

        // refund the rest
        $paymentService->initiate(['amount' => '100.00']);
        $this->assertEquals('Refunded', $payment->Status);
        $this->assertEquals('100.00', $payment->MoneyAmount);
        $this->assertFalse($payment->canRefund(null, true));
    }

    public function testPartialRefundViaNotification(): void
    {
        // load a payment from fixture
        $payment = $this->objFromFixture(Payment::class, $this->fixtureIdentifier);

        // use notification on the gateway
        Config::modify()->merge(GatewayInfo::class, $payment->Gateway, [
            'use_async_notification' => true
        ]);

        $stubGateway = $this->buildPaymentGatewayStub(false, $this->fixtureReceipt);
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), GatewayFactory::class);

        $service = $this->getService($payment);
        $service->getExtensionInstance(PaymentTestServiceExtensionHooks::class)->Reset();
        $service->initiate(['amount' => '669.50']);

        // payment amount should still be the full amount!
        $this->assertEquals('769.50', $payment->MoneyAmount);

        // there must be a partial payment
        $this->assertEquals(1, $payment->getPartialPayments()->count());

        // the partial payment should be pending and negative
        $partialPayment = $payment->getPartialPayments()->first();
        $this->assertEquals('PendingRefund', $partialPayment->Status);
        $this->assertEquals('-669.50', $partialPayment->MoneyAmount);

        // Now a notification comes in
        $this->get('paymentendpoint/' . $payment->Identifier . '/notify');

        // ensure payment hooks were called
        $this->assertEquals(
            $this->successPaymentExtensionHooks,
            PaymentTestPaymentExtensionHooks::findExtensionForID($payment->ID)->getCalledMethods()
        );

        // ensure the correct service hooks were called
        $this->assertEquals(
            array_merge($this->initiateServiceExtensionHooks, ['updatePartialPayment', 'updateServiceResponse']),
            $service->getExtensionInstance(PaymentTestServiceExtensionHooks::class)->getCalledMethods()
        );

        // we'll have to "reload" the payment from the DB now
        $payment = Payment::get()->byID($payment->ID);

        // Status should still be captured
        $this->assertEquals('Captured', $payment->Status);
        // the payment balance is reduced to 100.00
        $this->assertEquals('100.00', $payment->MoneyAmount);
        $this->assertInstanceOf(Payment::class, $payment);

        // the partial payment should no longer be pending and positive
        $partialPayment = $payment->getPartialPayments()->first();
        $this->assertEquals('Refunded', $partialPayment->Status);
        $this->assertEquals('669.50', $partialPayment->MoneyAmount);
        $this->assertInstanceOf(Payment::class, $payment);

        // check existance of messages
        SapphireTest::assertListContains([
            [
                'Type' => PurchaseService::MESSAGE_PURCHASED_RESPONSE,
                'Reference' => 'paymentReceipt'
            ],
            [
                'Type' => RefundService::MESSAGE_REFUND_REQUEST,
                'Reference' => 'paymentReceipt'
            ],
            [
                'Type' => PaymentService::MESSAGE_NOTIFICATION_SUCCESSFUL,
                'Reference' => 'paymentReceipt'
            ],
            [
                'Type' => RefundService::MESSAGE_PARTIALLY_REFUNDED_RESPONSE,
                'Reference' => 'paymentReceipt'
            ]
        ], $payment->Messages());

        // try to complete a second time
        $service = $this->getService($payment);
        $serviceResponse = $service->complete();

        // the service should respond with an error, since the payment is not (fully) refunded
        $this->assertTrue($serviceResponse->isError());
        // since the payment is already completed, we should not touch omnipay again.
        $this->assertNull($serviceResponse->getOmnipayResponse());
    }

    public function testMultipleInitiateCallsBeforeNotificationArrives(): void
    {
        // load a payment from fixture
        $payment = $this->objFromFixture(Payment::class, $this->fixtureIdentifier);

        // use notification on the gateway
        Config::modify()->merge(GatewayInfo::class, $payment->Gateway, [
            'use_async_notification' => true
        ]);

        $stubGateway = $this->buildPaymentGatewayStub(false, $this->fixtureReceipt);
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), GatewayFactory::class);

        $paymentService = $this->getService($payment);

        // try to initiate two refunds without waiting for one to complete
        $paymentService->initiate(['amount' => '100.00']);

        $exception = null;
        try {
            // the second attempt must throw an exception!
            $paymentService->initiate(['amount' => '69.50']);
        } catch (Exception $ex) {
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
        SapphireTest::assertListContains([
            [
                'Type' => PurchaseService::MESSAGE_PURCHASED_RESPONSE,
                'Reference' => 'paymentReceipt'
            ],
            [
                'Type' => RefundService::MESSAGE_REFUND_REQUEST,
                'Reference' => 'paymentReceipt'
            ]
        ], $payment->Messages());
    }

    public function testLargerAmount(): void
    {
        $stubGateway = $this->buildPaymentGatewayStub(true, $this->fixtureReceipt);
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), GatewayFactory::class);

        // load a captured payment from fixture
        $payment = $this->objFromFixture(Payment::class, $this->fixtureIdentifier);
        $paymentService = $this->getService($payment);

        // We supply the amount, but specify an amount that is way over what was captured
        // This will throw an InvalidParameterException
        $this->expectException(InvalidParameterException::class);
        $paymentService->initiate(['amount' => '1000000.00']);
    }

    public function testInvalidAmount(): void
    {
        $stubGateway = $this->buildPaymentGatewayStub(true, $this->fixtureReceipt);
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), GatewayFactory::class);

        // load a captured payment from fixture
        $payment = $this->objFromFixture(Payment::class, $this->fixtureIdentifier);
        $paymentService = $this->getService($payment);

        // We supply the amount, but specify an amount that is not a number
        // This will throw an InvalidParameterException
        $this->expectException(InvalidParameterException::class);
        $paymentService->initiate(['amount' => 'test']);
    }

    public function testNegativeAmount(): void
    {
        $stubGateway = $this->buildPaymentGatewayStub(true, $this->fixtureReceipt);
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), GatewayFactory::class);

        // load a captured payment from fixture
        $payment = $this->objFromFixture(Payment::class, $this->fixtureIdentifier);
        $paymentService = $this->getService($payment);

        // We supply the amount, but specify an amount that is not a positive number
        // This will throw an InvalidParameterException
        $this->expectException(InvalidParameterException::class);
        $paymentService->initiate(['amount' => '-100']);
    }

    public function testPartialRefundUnsupported(): void
    {
        $stubGateway = $this->buildPaymentGatewayStub(true, $this->fixtureReceipt);
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), GatewayFactory::class);

        // load a captured payment from fixture
        $payment = $this->objFromFixture(Payment::class, $this->fixtureIdentifier);
        $paymentService = $this->getService($payment);

        // only allow full refunds, thus disabling partial refunds
        Config::modify()->merge(GatewayInfo::class, $payment->Gateway, [
           'can_refund' => 'full'
        ]);

        // We supply a partial amount
        // This will throw an InvalidParameterException
        $this->expectException(InvalidParameterException::class);
        $paymentService->initiate(['amount' => '10.00']);
    }

    public function testPartialRefundFailed(): void
    {
        $stubGateway = $this->buildPaymentGatewayStub(false, $this->fixtureReceipt);
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), GatewayFactory::class);

        // load a captured payment from fixture
        $payment = $this->objFromFixture(Payment::class, $this->fixtureIdentifier);
        $paymentService = $this->getService($payment);

        $paymentService->initiate(['amount' => '100.00']);

        // there should be NO partial payments
        $this->assertEquals(0, $payment->getPartialPayments()->count());

        // Payment should be unaltered
        $this->assertEquals('Captured', $payment->Status);
        $this->assertEquals('769.50', $payment->MoneyAmount);
    }

    public function testPartialRefundViaNotificationFailed(): void
    {
        // load a payment from fixture
        $payment = $this->objFromFixture(Payment::class, $this->fixtureIdentifier);

        // use notification on the gateway
        Config::modify()->merge(GatewayInfo::class, $payment->Gateway, [
            'use_async_notification' => true
        ]);

        $stubGateway = $this->buildPaymentGatewayStub(
            false,
            $this->fixtureReceipt,
            NotificationInterface::STATUS_FAILED
        );

        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), GatewayFactory::class);

        $paymentService = $this->getService($payment);

        $paymentService->initiate(['amount' => '669.50']);

        // Now a notification comes in (will fail)
        $this->get('paymentendpoint/' . $payment->Identifier . '/notify');

        // we'll have to "reload" the payment from the DB now
        $payment = Payment::get()->byID($payment->ID);

        // Status should be reset
        $this->assertEquals('Captured', $payment->Status);
        // the payment balance is unaltered
        $this->assertEquals('769.50', $payment->MoneyAmount);
        $this->assertInstanceOf(Payment::class, $payment);

        // the partial payment should be void
        $partialPayment = $payment->getPartialPayments()->first();
        $this->assertEquals('Void', $partialPayment->Status);
        $this->assertEquals('-669.50', $partialPayment->MoneyAmount);
        $this->assertInstanceOf(Payment::class, $payment);

        // check existance of messages
        SapphireTest::assertListContains($this->notificationFailureMessages, $payment->Messages());
    }
}
