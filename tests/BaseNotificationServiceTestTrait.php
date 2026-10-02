<?php

declare(strict_types=1);

namespace SilverStripe\Omnipay\Tests;

use Omnipay\Common\GatewayFactory;
use Omnipay\Common\AbstractGateway;
use SilverStripe\Omnipay\Exception\InvalidConfigurationException;
use SilverStripe\Omnipay\Exception\InvalidStateException;
use SilverStripe\Omnipay\Exception\MissingParameterException;
use Omnipay\Common\Message\AbstractRequest;
use SilverStripe\Omnipay\Model\Message\PaymentMessage;
use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Exception\RuntimeException;
use Omnipay\Common\Http\ClientInterface;
use Omnipay\Common\Message\NotificationInterface;
use SilverStripe\Core\Config\Config;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Tests\Extensions\PaymentTestPaymentExtensionHooks;
use SilverStripe\Omnipay\Tests\Extensions\PaymentTestServiceExtensionHooks;
use SilverStripe\Omnipay\Tests\Model\TestOffsiteGateway;

/**
 * Base class with common tests for Void, Capture and Refund Services.
 * Configure variables in the test class.
 */
trait BaseNotificationServiceTestTrait
{
    public function testSuccess(): void
    {
        // load an authorized payment from fixture
        $payment = $this->objFromFixture(Payment::class, $this->fixtureIdentifier);

        $stubGateway = $this->buildPaymentGatewayStub(true, $this->fixtureReceipt);

        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), GatewayFactory::class);

        $service = $this->getService($payment);

        $serviceResponse = $service->initiate();

        // the service should not respond with an error
        $this->assertFalse($serviceResponse->isError());
        // we should get a successful Omnipay response
        $this->assertNotNull($serviceResponse->getOmnipayResponse());
        $this->assertTrue($serviceResponse->getOmnipayResponse()->isSuccessful());
        // check payment status
        $this->assertEquals($payment->Status, $this->endStatus, 'Payment status should be set to ' . $this->endStatus);

        // check existence of messages and existence of references
        SapphireTest::assertListContains($this->successFromFixtureMessages, $payment->Messages());

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

    public function testManualSuccess(): void
    {
        // Use a manual payment (this payment doesn't have any previous messages to grab transaction reference from)
        $payment = $this->payment->setGateway('Manual');
        $payment->Status = $this->startStatus;

        $stubGateway = $this->buildPaymentGatewayStub(true, 'testThisRecipe123');
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), GatewayFactory::class);

        $service = $this->getService($payment);

        // Manual payments should succeed, even when there's no transaction reference given!
        $serviceResponse = $service->initiate();

        // the service should not respond with an error
        $this->assertFalse($serviceResponse->isError());

        // we should get a successful Omnipay response
        $this->assertNotNull($serviceResponse->getOmnipayResponse());
        $this->assertTrue($serviceResponse->getOmnipayResponse()->isSuccessful());

        // check payment status
        $this->assertSame($payment->Status, $this->endStatus, 'Payment status should be set to ' . $this->endStatus);

        // check existance of messages and existence of references
        SapphireTest::assertListContains($this->successMessages, $payment->Messages());

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

    public function testSuccessWithTransactionParameter(): void
    {
        // set the payment status to the desired start status
        $this->payment->Status = $this->startStatus;

        $stubGateway = $this->buildPaymentGatewayStub(true, 'testThisRecipe123');
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), GatewayFactory::class);

        $service = $this->getService($this->payment);

        // pass transaction reference as parameter
        $serviceResponse = $service->initiate(['transactionReference' => 'testThisRecipe123']);

        // the service should not respond with an error
        $this->assertFalse($serviceResponse->isError());
        // We should get a successful Omnipay response
        $this->assertNotNull($serviceResponse->getOmnipayResponse());
        $this->assertTrue($serviceResponse->getOmnipayResponse()->isSuccessful());
        // check payment status
        $this->assertEquals($this->payment->Status, $this->endStatus, 'Payment status should be set to ' . $this->endStatus);

        // check existance of messages and existence of references
        SapphireTest::assertListContains($this->successMessages, $this->payment->Messages());

        // ensure payment hooks were called
        $this->assertEquals(
            $this->successPaymentExtensionHooks,
            $this->payment->getExtensionInstance(PaymentTestPaymentExtensionHooks::class)->getCalledMethods()
        );

        // ensure the correct service hooks were called
        $this->assertEquals(
            $this->initiateServiceExtensionHooks,
            $service->getExtensionInstance(PaymentTestServiceExtensionHooks::class)->getCalledMethods()
        );
    }

    public function testSuccessWithLegacyTransactionParameter(): void
    {
        // set the payment status to the desired start status
        $this->payment->Status = $this->startStatus;

        $stubGateway = $this->buildPaymentGatewayStub(true, 'testThisRecipe123');
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), GatewayFactory::class);

        $service = $this->getService($this->payment);

        // pass transaction reference as parameter
        $serviceResponse = $service->initiate(['receipt' => 'testThisRecipe123']);

        // the service should not respond with an error
        $this->assertFalse($serviceResponse->isError());
        // We should get a successful Omnipay response
        $this->assertNotNull($serviceResponse->getOmnipayResponse());
        $this->assertTrue($serviceResponse->getOmnipayResponse()->isSuccessful());
        // check payment status
        $this->assertEquals($this->payment->Status, $this->endStatus, 'Payment status should be set to ' . $this->endStatus);

        // check existance of messages and existence of references
        SapphireTest::assertListContains($this->successMessages, $this->payment->Messages());

        // ensure payment hooks were called
        $this->assertEquals(
            $this->successPaymentExtensionHooks,
            $this->payment->getExtensionInstance(PaymentTestPaymentExtensionHooks::class)->getCalledMethods()
        );

        // ensure the correct service hooks were called
        $this->assertEquals(
            $this->initiateServiceExtensionHooks,
            $service->getExtensionInstance(PaymentTestServiceExtensionHooks::class)->getCalledMethods()
        );
    }

    public function testSuccessViaNotification(): void
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
        // pass transaction reference as parameter
        $serviceResponse = $service->initiate();

        // the service should not respond with an error
        $this->assertFalse($serviceResponse->isError());
        // When waiting for a notification, request won't be successful from Omnipays point of view
        $this->assertNotNull($serviceResponse->getOmnipayResponse());
        $this->assertFalse($serviceResponse->getOmnipayResponse()->isSuccessful());
        // response should have the "AwaitingNotification" flag
        $this->assertTrue($serviceResponse->isAwaitingNotification());
        // check payment status
        $this->assertEquals(
            $payment->Status,
            $this->pendingStatus,
            'Payment status should be set to ' . $this->pendingStatus
        );

        // check existance of messages and existence of references.
        // Since operation isn't complete, we shave off the latest message from the exptected messages!
        SapphireTest::assertListContains(array_slice($this->successFromFixtureMessages, 0, -1), $payment->Messages());

        // Now a notification comes in
        $response = $this->get('paymentendpoint/' . $payment->Identifier . '/notify');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals("OK", $response->getBody());

        // ensure payment hooks were called
        $this->assertEquals(
            $this->successPaymentExtensionHooks,
            PaymentTestPaymentExtensionHooks::findExtensionForID($payment->ID)->getCalledMethods()
        );

        // ensure the correct service hooks were called
        $this->assertEquals(
            array_merge($this->initiateServiceExtensionHooks, ['updateServiceResponse']),
            $service->getExtensionInstance(PaymentTestServiceExtensionHooks::class)->getCalledMethods()
        );

        // we'll have to "reload" the payment from the DB now
        $payment = Payment::get()->byID($payment->ID);
        $this->assertEquals($payment->Status, $this->endStatus, 'Payment status should be set to ' . $this->endStatus);
        $this->assertInstanceOf(Payment::class, $payment);

        // check existance of messages
        SapphireTest::assertListContains($this->successFromFixtureMessages, $payment->Messages());

        $service->getExtensionInstance(PaymentTestServiceExtensionHooks::class)->Reset();
        // try to complete a second time
        $service = $this->getService($payment);
        $serviceResponse = $service->complete();

        // the service should not respond with an error
        $this->assertFalse($serviceResponse->isError());
        // since the payment is already completed, we should not touch omnipay again.
        $this->assertNull($serviceResponse->getOmnipayResponse());
        // should not be waiting for notification
        $this->assertFalse($serviceResponse->isAwaitingNotification());
        // must always be true
        $this->assertTrue($serviceResponse->isNotification());

        // only a service response will be generated, as omnipay is no longer involved at this stage
        $this->assertEquals(
            ['updateServiceResponse'],
            $service->getExtensionInstance(PaymentTestServiceExtensionHooks::class)->getCalledMethods()
        );
    }

    public function testFailure(): void
    {
        // load an authorized payment from fixture
        $payment = $this->objFromFixture(Payment::class, $this->fixtureIdentifier);

        $stubGateway = $this->buildPaymentGatewayStub(false, $this->fixtureReceipt);
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), GatewayFactory::class);

        $service = $this->getService($payment);

        $serviceResponse = $service->initiate();

        // the service should respond with an error
        $this->assertTrue($serviceResponse->isError());

        // Omnipay response should be unsuccessful
        $this->assertNotNull($serviceResponse->getOmnipayResponse());
        $this->assertFalse($serviceResponse->getOmnipayResponse()->isSuccessful());

        // payment status should be unchanged
        $this->assertEquals($payment->Status, $this->startStatus, 'Payment status should be unchanged');

        // check existance of messages and existence of references
        SapphireTest::assertListContains($this->failureMessages, $payment->Messages());

        // ensure payment hooks were called
        $this->assertEquals(
            [],
            $payment->getExtensionInstance(PaymentTestPaymentExtensionHooks::class)->getCalledMethods()
        );

        // ensure the correct service hooks were called
        $this->assertEquals(
            $this->initiateServiceExtensionHooks,
            $service->getExtensionInstance(PaymentTestServiceExtensionHooks::class)->getCalledMethods()
        );
    }

    public function testGatewayFailure(): void
    {
        // load an authorized payment from fixture
        /** @var Payment $payment */
        $payment = $this->objFromFixture(Payment::class, $this->fixtureIdentifier);

        $stubGateway = $this->buildPaymentGatewayStub(
            false,
            $this->fixtureReceipt,
            NotificationInterface::STATUS_COMPLETED,
            true
        );
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), GatewayFactory::class);

        $service = $this->getService($payment);

        $serviceResponse = $service->initiate();

        // the service should respond with an error
        $this->assertTrue($serviceResponse->isError());
        // There should be no omnipay response, as the gateway threw an exception
        $this->assertNull($serviceResponse->getOmnipayResponse());
        // payment status should be unchanged
        $this->assertEquals($payment->Status, $this->startStatus, 'Payment status should be unchanged');

        $msg = $payment->getLatestMessageOfType($this->errorMessageType);

        $this->assertInstanceOf(PaymentMessage::class, $msg, 'An error message should have been generated');
        $this->assertEquals('Mock Send Exception', $msg->Message);

        // ensure payment hooks were called
        $this->assertEquals(
            [],
            $payment->getExtensionInstance(PaymentTestPaymentExtensionHooks::class)->getCalledMethods()
        );

        // ensure the correct service hooks were called
        $this->assertEquals(
            $this->initiateFailedServiceExtensionHooks,
            $service->getExtensionInstance(PaymentTestServiceExtensionHooks::class)->getCalledMethods()
        );
    }

    public function testUnsupportedGatewayMethod(): void
    {
        // Build the dummy gateway that doesn't contain the requested method (eg. void, capture or refund)
        $stubGateway = $this->getMockBuilder(AbstractGateway::class)
            ->onlyMethods(['getName'])
            ->getMock();

        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), GatewayFactory::class);

        $this->payment->Status = $this->startStatus;
        $service = $this->getService($this->payment);

        // this should throw an exception, because the gateway doesn't support the method
        $this->expectException(InvalidConfigurationException::class);
        $service->initiate(['receipt' => 'testThisRecipe123']);
    }

    public function testFailureViaNotification(): void
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

        $service = $this->getService($payment);
        $service->initiate();

        // Now a notification comes in (will fail)
        $this->get('paymentendpoint/' . $payment->Identifier . '/notify');

        // we'll have to "reload" the payment from the DB now
        $payment = Payment::get()->byID($payment->ID);

        // Status should be reset
        $this->assertEquals($this->startStatus, $payment->Status);
        $this->assertInstanceOf(Payment::class, $payment);

        // check existance of messages
        SapphireTest::assertListContains($this->notificationFailureMessages, $payment->Messages());
    }

    public function testGatewayNotificationFailure(): void
    {
        $payment = $this->objFromFixture(Payment::class, $this->fixtureIdentifier);

        $stubGateway = $this->buildPaymentGatewayStub(
            true,
            $this->fixtureReceipt,
            NotificationInterface::STATUS_COMPLETED,
            true
        );
        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), GatewayFactory::class);

        $payment->Status = $this->pendingStatus;
        $service = $this->getService($payment);

        $serviceResponse = $service->complete();

        // the service should respond with an error
        $this->assertTrue($serviceResponse->isError());
        // There should be no omnipay notification, as the gateway threw an exception
        $this->assertNull($serviceResponse->getOmnipayResponse());
        // payment status should be unchanged
        $this->assertSame($payment->Status, $this->pendingStatus, 'Payment status should be unchanged');

        // ensure payment hooks were called
        $this->assertEquals(
            [],
            $payment->getExtensionInstance(PaymentTestPaymentExtensionHooks::class)->getCalledMethods()
        );

        // only a service response will be generated with the notification
        $this->assertEquals(
            ['updateServiceResponse'],
            $service->getExtensionInstance(PaymentTestServiceExtensionHooks::class)->getCalledMethods()
        );
    }

    public function testNotificationTransactionReferenceMismatch(): void
    {
        $payment = $this->objFromFixture(Payment::class, $this->fixtureIdentifier);

        // create gateway but use a different transaction reference
        $stubGateway = $this->buildPaymentGatewayStub(true, 'DifferentReference');

        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), GatewayFactory::class);

        $payment->Status = $this->pendingStatus;
        $service = $this->getService($payment);

        $serviceResponse = $service->complete();

        // the service should respond with an error
        $this->assertTrue($serviceResponse->isError());
        // There should be an omnipay notification
        $this->assertNotNull($serviceResponse->getOmnipayResponse());
        $this->assertInstanceOf(
            NotificationInterface::class,
            $serviceResponse->getOmnipayResponse()
        );
        // payment status should be unchanged
        $this->assertSame($payment->Status, $this->pendingStatus, 'Payment status should be unchanged');
    }

    public function testInvalidStatus(): void
    {
        $this->payment->Status = 'Created';

        // create a service with a payment that is created
        $service = $this->getService($this->payment);

        // this should throw an exception
        $this->expectException(InvalidConfigurationException::class);
        $service->initiate();
    }

    public function testInvalidCompleteStatus(): void
    {
        $this->payment->Status = 'Created';

        // create a service with a payment that is created
        $service = $this->getService($this->payment);

        // this should throw an exception
        $this->expectException(InvalidStateException::class);
        $service->complete();
    }

    public function testMissingTransactionReference(): void
    {
        $this->payment->Status = $this->startStatus;

        // create a service with a payment that has the correct status
        // but doesn't have any transaction references in messages
        $service = $this->getService($this->payment);

        // this should throw an exception
        $this->expectException(MissingParameterException::class);
        $service->initiate();
    }


    public function testMethodDisabled(): void
    {
        // disallow the service via config
        $method = 'allow_' . $this->gatewayMethod;
        Config::modify()->merge(GatewayInfo::class, 'Dummy', [
            $method => false
        ]);
        $this->payment->setGateway('Dummy')->Status = 'Created';

        // create a service with a payment that is created
        $service = $this->getService($this->payment);

        // this should throw an exception
        $this->expectException(InvalidConfigurationException::class);
        $service->initiate();
    }


    protected function buildPaymentGatewayStub(
        $successValue,
        $transactionReference,
        $returnState = NotificationInterface::STATUS_COMPLETED,
        $throwGatewayException = false
    ) {
        //--------------------------------------------------------------------------------------------------------------
        // void request and response

        $mockResponse = $this->createMock(AbstractResponse::class);

        $mockResponse
            ->method('isSuccessful')->willReturn($successValue);

        $mockResponse
            ->method('getTransactionReference')->willReturn($transactionReference);

        $mockRequest = $this->getMockBuilder(AbstractRequest::class)
            ->setConstructorArgs([
                $this->createStub(ClientInterface::class),
                SymfonyRequest::create('/'),
            ])
            ->onlyMethods(['send', 'sendData', 'getData', 'getTransactionReference'])
            ->getMock();

        if ($throwGatewayException) {
            $mockRequest->method('send')->willThrowException(new RuntimeException('Mock Send Exception'));
        } else {
            $mockRequest
                ->method('send')->willReturn($mockResponse);
        }

        $mockRequest
            ->method('getTransactionReference')->willReturn($transactionReference);

        //--------------------------------------------------------------------------------------------------------------
        // Notification

        $notificationResponse = $this->createMock(NotificationInterface::class);

        $notificationResponse
            ->method('getTransactionStatus')->willReturn($returnState);

        $notificationResponse
            ->method('getTransactionReference')->willReturn($transactionReference);


        //--------------------------------------------------------------------------------------------------------------
        // Build the gateway

        $stubGateway = $this->getMockBuilder(TestOffsiteGateway::class)
            ->onlyMethods([
                'getName',
                'acceptNotification',
                'capture',
                'refund',
                'void'
            ])->getMock();

        $stubGateway
            ->method($this->gatewayMethod)
            ->willReturn($mockRequest);

        $stubGateway
            ->method('acceptNotification')
            ->willReturn(
                $throwGatewayException
                    ? $this->throwException(new RuntimeException('Mock Notification Exception'))
                    : $notificationResponse
            );

        return $stubGateway;
    }
}
