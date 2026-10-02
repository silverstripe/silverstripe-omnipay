<?php

declare(strict_types=1);

namespace SilverStripe\Omnipay\Tests;

use SilverStripe\Omnipay\Exception\InvalidStateException;
use Omnipay\Common\AbstractGateway;
use Omnipay\Common\GatewayFactory;
use SilverStripe\Omnipay\Exception\InvalidConfigurationException;
use Omnipay\Common\Exception\RuntimeException;
use Omnipay\PaymentExpress\Message\Response;
use Omnipay\PaymentExpress\Message\PxPayPurchaseRequest;
use Omnipay\PaymentExpress\Message\PxPayCompleteAuthorizeRequest;
use Omnipay\Common\Message\AbstractResponse;
use PHPUnit\Framework\MockObject\MockObject;
use Omnipay\Common\Message\AbstractRequest;
use Omnipay\Common\Http\ClientInterface;
use SilverStripe\Dev\SapphireTest;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use SilverStripe\Omnipay\GatewayInfo;
use Symfony\Component\HttpFoundation\RedirectResponse;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Config\Config;
use SilverStripe\Omnipay\Tests\Extensions\PaymentTestServiceExtensionHooks;
use SilverStripe\Omnipay\Tests\Extensions\PaymentTestPaymentExtensionHooks;
use SilverStripe\Omnipay\Tests\Model\TestOffsiteGateway;
use Closure;

/**
 * Base class that implements common tests for "authorize" and "purchase".
 * Configure variables in the test class.
 */
trait BasePurchaseServiceTestTrait
{
    public function testDummyOnSitePayment(): void
    {
        $payment = $this->payment;
        $service = $this->getService($payment);

        $response = $service->initiate([
            'firstName' => 'joe',
            'lastName' => 'bloggs',
            'number' => '4242424242424242', //this creditcard will succeed
            'expiryMonth' => '5',
            'expiryYear' => date("Y", strtotime("+1 year"))
        ]);

        $this->assertEquals($this->completeStatus, $payment->Status, "is the status updated");
        $this->assertSame(1222, (int) $payment->Amount);
        $this->assertEquals("GBP", $payment->Currency);
        $this->assertEquals("Dummy", $payment->Gateway);
        $this->assertTrue($response->getOmnipayResponse()->isSuccessful());
        $this->assertFalse($response->isRedirect());
        $this->assertFalse($response->isError());
        $this->assertFalse($response->isCancelled());
        $this->assertFalse($response->isAwaitingNotification());
        $this->assertFalse($response->isNotification());

        //values cannot be changed after successful payment
        $payment->Amount = 2;
        $payment->Currency = "NZD";
        $payment->Gateway = "XYZ";
        $payment->write();

        $this->assertSame(1222, (int) $payment->Amount);
        $this->assertSame("GBP", $payment->Currency);
        $this->assertSame("Dummy", $payment->Gateway);

        //check messaging
        SapphireTest::assertListContains($this->onsiteSuccessMessages, $payment->Messages());

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

    public function testFailedDummyOnSitePayment(): void
    {
        $payment = $this->payment;
        $service = $this->getService($payment);
        $response = $service->initiate([
            'firstName' => 'joe',
            'lastName' => 'bloggs',
            'number' => '4111111111111111',  //this creditcard will decline
            'expiryMonth' => '5',
            'expiryYear' => date("Y", strtotime("+1 year"))
        ]);
        $this->assertEquals("Created", $payment->Status, "is the status has not been updated");
        $this->assertEquals(1222, $payment->Amount);
        $this->assertEquals("GBP", $payment->Currency);
        $this->assertFalse($response->getOmnipayResponse()->isSuccessful());
        $this->assertTrue($response->isError());
        $this->assertFalse($response->isRedirect());

        //check messaging
        SapphireTest::assertListContains($this->onsiteFailMessages, $payment->Messages());

        // no extension hook will be called on payment
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

    public function testOnSitePayment(): void
    {
        $payment = $this->payment->setGateway('PaymentExpress_PxPost');
        $service = $this->getService($payment);
        $this->setMockHttpResponse('paymentexpress/tests/Mock/PxPostPurchaseSuccess.txt');//add success mock response from file
        $response = $service->initiate([
            'firstName' => 'joe',
            'lastName' => 'bloggs',
            'number' => '4242424242424242', //this creditcard will succeed
            'expiryMonth' => '5',
            'expiryYear' => date("Y", strtotime("+1 year"))
        ]);
        $this->assertTrue($response->getOmnipayResponse()->isSuccessful());
        $this->assertFalse($response->isRedirect());
        $this->assertFalse($response->isError());
        $this->assertSame($this->completeStatus, $payment->Status);

        //check messaging
        SapphireTest::assertListContains($this->onsiteSuccessMessages, $payment->Messages());

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

    public function testInvalidOnsitePayment(): void
    {
        $payment = $this->payment->setGateway("PaymentExpress_PxPost");
        $service = $this->getService($payment);
        //pass no card details nothing
        $response = $service->initiate([]);

        //check messaging
        $this->assertFalse($response->isRedirect());
        $this->assertTrue($response->isError());
        SapphireTest::assertListContains($this->failMessages, $payment->Messages());

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

    public function testFailedOnSitePayment(): void
    {
        $payment = $this->payment->setGateway('PaymentExpress_PxPost');
        $service = $this->getService($payment);
        $this->setMockHttpResponse('paymentexpress/tests/Mock/PxPostPurchaseFailure.txt');//add success mock response from file
        $response = $service->initiate([
            'number' => '4111111111111111', //this creditcard will decline
            'expiryMonth' => '5',
            'expiryYear' => date("Y", strtotime("+1 year"))
        ]);
        $this->assertFalse($response->getOmnipayResponse()->isSuccessful()); // capturing/authorization wasn't successful
        $this->assertFalse($response->isRedirect());
        $this->assertTrue($response->isError());
        $this->assertSame("Created", $payment->Status);

        //check messaging
        SapphireTest::assertListContains($this->onsiteFailMessages, $payment->Messages());

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

    public function testOffSitePayment(): void
    {
        $payment = $this->payment->setGateway('PaymentExpress_PxPay');
        $service = $this->getService($payment);
        $this->setMockHttpResponse('paymentexpress/tests/Mock/PxPayPurchaseSuccess.txt');//add success mock response from file
        $response = $service->initiate();
        $this->assertFalse($response->getOmnipayResponse()->isSuccessful()); // capturing/authorization wasn't successful
        $this->assertTrue($response->isRedirect());
        $this->assertFalse($response->isError()); // this should not be considered to be an error

        $this->assertSame(
            'https://sec.windcave.com/pxpay/pxpay.aspx?userid=Developer&request=v5H7JrBTzH-4Whs__1iQnz4RGSb9qxRKNR4kIuDP8kIkQzIDiIob9GTIjw_9q_AdRiR47ViWGVx40uRMu52yz2mijT39YtGeO7cZWrL5rfnx0Mc4DltIHRnIUxy1EO1srkNpxaU8fT8_1xMMRmLa-8Fd9bT8Oq0BaWMxMquYa1hDNwvoGs1SJQOAJvyyKACvvwsbMCC2qJVyN0rlvwUoMtx6gGhvmk7ucEsPc_Cyr5kNl3qURnrLKxINnS0trdpU4kXPKOlmT6VacjzT1zuj_DnrsWAPFSFq-hGsow6GpKKciQ0V0aFbAqECN8rl_c-aZWFFy0gkfjnUM4qp6foS0KMopJlPzGAgMjV6qZ0WfleOT64c3E-FRLMP5V_-mILs8a',
            $response->getTargetUrl()
        );
        // Status should be set to pending
        $this->assertSame($this->pendingStatus, $payment->Status);

        //... user would normally be redirected to external gateway at this point ...

        //mock complete Payment response
        $this->setMockHttpResponse('paymentexpress/tests/Mock/PxPayCompletePurchaseSuccess.txt');
        //mock the 'result' get variable into the current request
        $this->getHttpRequest()->query->replace(['result' => 'abc123']);
        $response = $service->complete();
        $this->assertTrue($response->getOmnipayResponse()->isSuccessful());
        $this->assertSame($this->completeStatus, $payment->Status);
        $this->assertFalse($response->isError());
        // payment should get the transaction reference from Omnipay assigned
        $reference = $response->getOmnipayResponse()->getTransactionReference();
        $this->assertNotNull($reference);
        $this->assertEquals($payment->TransactionReference, $reference);

        //check messaging
        SapphireTest::assertListContains($this->offsiteSuccessMessages, $payment->Messages());

        // ensure payment hooks were called
        $this->assertEquals(
            $this->successPaymentExtensionHooks,
            $payment->getExtensionInstance(PaymentTestPaymentExtensionHooks::class)->getCalledMethods()
        );

        // ensure the correct service hooks were called
        $this->assertEquals(
            array_merge($this->initiateServiceExtensionHooks, $this->completeServiceExtensionHooks),
            $service->getExtensionInstance(PaymentTestServiceExtensionHooks::class)->getCalledMethods()
        );
    }

    public function testFailedOffSitePayment(): void
    {
        $payment = $this->payment->setGateway('PaymentExpress_PxPay');
        $service = $this->getService($payment);
        $this->setMockHttpResponse('paymentexpress/tests/Mock/PxPayPurchaseFailure.txt');//add success mock response from file
        $response = $service->initiate();
        $this->assertFalse($response->getOmnipayResponse()->isSuccessful()); // capturing/authorization wasn't successful
        $this->assertFalse($response->isRedirect()); //redirect won't occur, because of failure
        $this->assertTrue($response->isError());
        $this->assertSame("Created", $payment->Status);

        //check messaging.
        // We use the onsite fail messages here, since the payment fails *before* we redirect to the offsite gateway.
        // Therefore this should generate the same messages as an onsite-payment failure.
        SapphireTest::assertListContains($this->onsiteFailMessages, $payment->Messages());

        $this->assertEquals(
            [],
            $payment->getExtensionInstance(PaymentTestPaymentExtensionHooks::class)->getCalledMethods()
        );

        // ensure the correct service hooks were called (only the initiate phase will complete)
        $this->assertEquals(
            $this->initiateServiceExtensionHooks,
            $service->getExtensionInstance(PaymentTestServiceExtensionHooks::class)->getCalledMethods()
        );
    }

    public function testFailedOffSiteCompletePayment(): void
    {
        $this->setMockHttpResponse(
            'paymentexpress/tests/Mock/PxPayCompletePurchaseFailure.txt'
        );
        //mock the 'result' get variable into the current request
        $this->getHttpRequest()->query->replace(['result' => 'abc123']);
        //mimic a redirect or request from offsite gateway
        $response = $this->get("paymentendpoint/$this->paymentId/complete");
        //redirect works
        $this->assertStringEndsWith(
            '/shop/incomplete',
            $response->getHeader('Location')
        );
        $payment = Payment::get()
            ->filter('Identifier', $this->paymentId)
            ->first();
        $this->assertInstanceOf(Payment::class, $payment);
        SapphireTest::assertListContains($this->offsiteFailMessages, $payment->Messages());

        $this->assertEquals(
            [],
            $payment->getExtensionInstance(PaymentTestPaymentExtensionHooks::class)->getCalledMethods()
        );
    }

    public function testNonExistantGateway(): void
    {
        //exception when trying to run functions that require a gateway
        $payment = $this->payment;
        $service = $this->getService(
            $payment->init("FantasyGateway", 100, "NZD")->setSuccessUrl("complete")
        );

        // Will throw an exception since the gateway doesn't exist
        $this->expectException(RuntimeException::class);
        $service->initiate();
    }

    public function testPaymentInvalidStatus(): void
    {
        $payment = $this->payment;
        $payment->Status = 'Void';

        $service = $this->getService($payment);
        $this->expectException(InvalidStateException::class);
        $service->initiate();
    }

    public function testCompletePaymentInvalidStatus(): void
    {
        $payment = $this->payment;
        $payment->Status = 'Void';

        $service = $this->getService($payment);
        $this->expectException(InvalidStateException::class);
        $service->complete();
    }


    public function testGatewayDoesntSupportMethod(): void
    {
        // Build the dummy gateway
        $stubGateway = $this->getMockBuilder(AbstractGateway::class)
            ->onlyMethods(['getName'])
            ->getMock();

        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), GatewayFactory::class);

        $this->payment->Status = 'Created';
        $service = $this->getService($this->payment);
        // this should throw an exception, because the gateway doesn't support the payment method
        $this->expectException(InvalidConfigurationException::class);
        $service->initiate();
    }


    public function testGatewayDoesntSupportCompleteMethod(): void
    {
        // Build the dummy gateway
        $stubGateway = $this->getMockBuilder(AbstractGateway::class)
            ->onlyMethods(['getName'])
            ->getMock();

        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), GatewayFactory::class);

        $this->payment->Status = $this->pendingStatus;
        $service = $this->getService($this->payment);
        // this should throw an exception, because the gateway doesn't support the complete method
        $this->expectException(InvalidConfigurationException::class);
        $service->complete();
    }

    public function testGatewayCompleteMethodFailure(): void
    {
        // build a stub gateway with the given endpoint
        $stubGateway = $this->buildPaymentGatewayStub('https://gateway.tld/endpoint', function (): true {
            return true;
        }, true);

        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), GatewayFactory::class);

        $this->payment->Status = $this->pendingStatus;
        $service = $this->getService($this->payment);

        // this should return an error response
        $serviceResponse = $service->complete();

        $this->assertTrue($serviceResponse->isError());
        $this->assertNull($serviceResponse->getOmnipayResponse());
        SapphireTest::assertListContains([
            [
                'Type' => $this->failureMessageType,
                'Message' => 'Mock Exception'
            ]
        ], $this->payment->Messages());

        $this->assertEquals(
            [],
            $this->payment->getExtensionInstance(PaymentTestPaymentExtensionHooks::class)->getCalledMethods()
        );

        // ensure the correct service hooks were called
        $this->assertEquals(
            $this->completeServiceExtensionHooks,
            $service->getExtensionInstance(PaymentTestServiceExtensionHooks::class)->getCalledMethods()
        );
    }


    public function testTokenGateway(): void
    {
        Config::modify()->merge(GatewayInfo::class, 'PaymentExpress_PxPost', [
            'token_key' => 'token'
        ]);

        $stubGateway = $this->getMockBuilder(TestOffsiteGateway::class)
            ->onlyMethods(['getName', $this->omnipayMethod])
            ->getMock();

        $stubGateway->expects($this->once())
            ->method($this->omnipayMethod)
            ->with(
                $this->logicalAnd(
                    $this->arrayHasKey('token'),
                    $this->callback(function (array $item): bool {
                        return $item['token'] == 'ABC123';
                    }),
                    $this->logicalNot($this->arrayHasKey('card'))
                )
            )
            ->willReturn($this->stubRequest());

        $payment = $this->payment->setGateway('PaymentExpress_PxPost');

        $service = $this->getService($payment);
        $service->setGatewayFactory($this->stubGatewayFactory($stubGateway));

        $service->initiate(['token' => 'ABC123']);
    }

    public function testTokenGatewayWithAlternateKey(): void
    {
        Config::modify()->merge(GatewayInfo::class, 'PaymentExpress_PxPost', [
            'token_key' => 'my_token'
        ]);
        $stubGateway = $this->getMockBuilder(TestOffsiteGateway::class)
            ->onlyMethods(['getName', $this->omnipayMethod])
            ->getMock();

        $stubGateway->expects($this->once())
            ->method($this->omnipayMethod)
            ->with(
                $this->logicalAnd(
                    $this->arrayHasKey('token'), // my_token should get normalized to this
                    $this->callback(function (array $item): bool {
                        return $item['token'] == 'ABC123';
                    }),
                    $this->logicalNot($this->arrayHasKey('card'))
                )
            )
            ->willReturn($this->stubRequest());

        $payment = $this->payment->setGateway('PaymentExpress_PxPost');

        $service = $this->getService($payment);
        $service->setGatewayFactory($this->stubGatewayFactory($stubGateway));

        $service->initiate(['my_token' => 'ABC123']);
    }

    public function testAsyncPaymentConfirmation(): void
    {
        Config::modify()->merge(GatewayInfo::class, 'PaymentExpress_PxPay', [
            'use_async_notification' => true
        ]);

        // build a stub gateway with the given endpoint
        $isNotification = false;
        $stubGateway = $this->buildPaymentGatewayStub('https://gateway.tld/endpoint', function () use (&$isNotification): bool {
            return $isNotification;
        });
        $payment = $this->payment->setGateway('PaymentExpress_PxPay');
        $payment->setFailureUrl('my/cancel/url')->setSuccessUrl('my/return/url');

        $service = $this->getService($payment);
        $service->setGatewayFactory($this->stubGatewayFactory($stubGateway));

        $serviceResponse = $service->initiate();

        // we should get a redirect
        $this->assertTrue($serviceResponse->isRedirect());
        // that redirect should point to the endpoint returned by omnipay
        $this->assertEquals('https://gateway.tld/endpoint', $serviceResponse->getTargetUrl());
        // Payment should be pending
        $this->assertEquals($payment->Status, $this->pendingStatus);

        $serviceResponse = $service->complete([], $isNotification);

        // since the confirmation will come in asynchronously, the gateway doesn't report success when coming back
        $this->assertFalse($serviceResponse->getOmnipayResponse()->isSuccessful(), 'Gateway will not return success');
        // Our application considers that fact and doesn't mark the service call as an error!
        $this->assertFalse($serviceResponse->isError());
        // We should get redirected to the success page now
        $this->assertEquals('my/return/url', $serviceResponse->getTargetUrl());
        // Payment status should still be pending
        $this->assertEquals($payment->Status, $this->pendingStatus);


        // simulate an incoming notification
        $isNotification = true;

        $serviceResponse = $service->complete([], $isNotification);

        // the response from the gateway should now be successful
        $this->assertTrue($serviceResponse->getOmnipayResponse()->isSuccessful(), 'Response should be successful');
        // Should not be an error
        $this->assertFalse($serviceResponse->isError());
        // We should get an HTTP response with "OK"
        $httpResponse = $serviceResponse->redirectOrRespond();
        $this->assertEquals('OK', $httpResponse->getBody());
        $this->assertEquals(200, $httpResponse->getStatusCode());
        // Payment status should be authorized or captured now (completed)
        $this->assertEquals($payment->Status, $this->completeStatus);

        // first the notification hook should be called, followed by the success hook
        $this->assertEquals(
            array_merge($this->notifyPaymentExtensionHooks, $this->successPaymentExtensionHooks),
            $payment->getExtensionInstance(PaymentTestPaymentExtensionHooks::class)->getCalledMethods()
        );

        // ensure the correct service hooks were called
        // complete will be called twice, once from returning from the offsite form and once via the notification
        $this->assertEquals(
            array_merge(
                $this->initiateServiceExtensionHooks,
                $this->completeServiceExtensionHooks,
                $this->completeServiceExtensionHooks
            ),
            $service->getExtensionInstance(PaymentTestServiceExtensionHooks::class)->getCalledMethods()
        );
    }

    // Test an async response that comes in before the user returns from the offsite form
    public function testAsyncPaymentConfirmationIncomingFirst(): void
    {
        Config::modify()->merge(GatewayInfo::class, 'PaymentExpress_PxPay', [
            'use_async_notification' => true
        ]);

        // build a stub gateway with the given endpoint
        $isNotification = true;
        $stubGateway = $this->buildPaymentGatewayStub('https://gateway.tld/endpoint', function () use (&$isNotification): true {
            return $isNotification;
        });
        $payment = $this->payment->setGateway('PaymentExpress_PxPay');
        $payment->setFailureUrl('my/cancel/url')->setSuccessUrl('my/return/url');

        $service = $this->getService($payment);
        $service->setGatewayFactory($this->stubGatewayFactory($stubGateway));

        $serviceResponse = $service->initiate();

        // we should get a redirect
        $this->assertTrue($serviceResponse->isRedirect());
        // Payment should be pending
        $this->assertEquals($payment->Status, $this->pendingStatus);

        // Notification comes in first!
        $isNotification = true;
        $serviceResponse = $service->complete([], $isNotification);

        // since we're getting the async notification now, payment should be successful
        $this->assertTrue($serviceResponse->getOmnipayResponse()->isSuccessful(), 'Response should be successful');
        // Should not be an error
        $this->assertFalse($serviceResponse->isError());
        // We should get an HTTP response with "OK"
        $httpResponse = $serviceResponse->redirectOrRespond();
        $this->assertEquals('OK', $httpResponse->getBody());
        $this->assertEquals(200, $httpResponse->getStatusCode());
        // Payment status should be captured or authorized (completed)
        $this->assertEquals($payment->Status, $this->completeStatus);

        // Now the user comes back from the offsite payment form
        $isNotification = false;
        $serviceResponse = $service->complete([], $isNotification);

        // We won't get an error, our payment is already complete
        $this->assertFalse($serviceResponse->isError());
        // There's no omnipay response since we no longer need to bother with omnipay at this point
        $this->assertNull($serviceResponse->getOmnipayResponse(), 'No omnipay response, payment already completed');
        // We should get redirected to the success page now
        $this->assertEquals('my/return/url', $serviceResponse->getTargetUrl());
        // Payment status should still be captured or authorized
        $this->assertEquals($payment->Status, $this->completeStatus);


        // only success hook will be called!
        $this->assertEquals(
            $this->successPaymentExtensionHooks,
            $payment->getExtensionInstance(PaymentTestPaymentExtensionHooks::class)->getCalledMethods()
        );

        // ensure the correct service hooks were called
        // complete will be called twice, but since the payment is already complete at that point,
        // only a service response will be generated
        $this->assertEquals(
            array_merge(
                $this->initiateServiceExtensionHooks,
                $this->completeServiceExtensionHooks,
                ['updateServiceResponse']
            ),
            $service->getExtensionInstance(PaymentTestServiceExtensionHooks::class)->getCalledMethods()
        );
    }

    // Test an async response that comes in before the user returns from the offsite form.
    // Test via PaymentGatewayController
    public function testPaymentGatewayControllerConfirmationIncomingFirst(): void
    {
        Config::modify()->merge(GatewayInfo::class, 'PaymentExpress_PxPay', [
            'use_async_notification' => true
        ]);

        // build a stub gateway with the given endpoint
        $isNotification = true;
        $stubGateway = $this->buildPaymentGatewayStub('https://gateway.tld/endpoint', function () use (&$isNotification): true {
            return $isNotification;
        });
        $payment = $this->payment->setGateway('PaymentExpress_PxPay');
        $payment->setFailureUrl('my/cancel/url')->setSuccessUrl('my/return/url');
        $service = $this->getService($payment);

        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), GatewayFactory::class);

        $serviceResponse = $service->initiate();

        // we should get a redirect
        $this->assertTrue($serviceResponse->isRedirect());
        // Payment should be pending
        $this->assertEquals($payment->Status, $this->pendingStatus);

        // Notification comes in first!
        $httpResponse = $this->get('paymentendpoint/' . $payment->Identifier . '/notify');

        $this->assertEquals('OK', $httpResponse->getBody());
        $this->assertEquals(200, $httpResponse->getStatusCode());

        // reload payment from DB
        $payment = Payment::get()->byID($payment->ID);
        // Payment status should be captured or authorized (completed)
        $this->assertEquals($payment->Status, $this->completeStatus);

        // Now the user comes back from the offsite payment form
        $httpResponse = $this->get('paymentendpoint/' . $payment->Identifier . '/complete');

        // we should be redirected to the success page
        $this->assertStringEndsWith('/my/return/url', $httpResponse->getHeader('Location'));
        $this->assertEquals(302, $httpResponse->getStatusCode());

        // reload payment from DB
        $payment = Payment::get()->byID($payment->ID);
        // Payment status should still be captured or authorized
        $this->assertEquals($payment->Status, $this->completeStatus);
    }

    protected function buildPaymentGatewayStub($endpoint, Closure $successFunc, $sendMustFail = false)
    {
        //--------------------------------------------------------------------------------------------------------------
        // Payment request and response

        $mockPaymentResponse = $this->createMock(Response::class);

        $mockPaymentResponse
            ->method('isRedirect')->willReturn(true);

        $mockPaymentResponse
            ->method('getRedirectResponse')
            ->willReturn(new RedirectResponse($endpoint));

        $mockPaymentRequest = $this->createMock(PxPayPurchaseRequest::class);

        if ($sendMustFail) {
            $mockPaymentRequest->method('send')->willThrowException(new RuntimeException('Mock Exception'));
        } else {
            $mockPaymentRequest->method('send')->willReturn($mockPaymentResponse);
        }

        //--------------------------------------------------------------------------------------------------------------
        // Complete Payment request and response

        $mockCompletePaymentResponse = $this->createMock(Response::class);

        // not successful, since we're waiting for async callback from the gateway
        $mockCompletePaymentResponse
            ->method('isSuccessful')->willReturnCallback($successFunc);

        $mockCompletePaymentRequest = $this->createMock(PxPayCompleteAuthorizeRequest::class);

        if ($sendMustFail) {
            $mockCompletePaymentRequest->method('send')->willThrowException(new RuntimeException('Mock Exception'));
        } else {
            $mockCompletePaymentRequest
                ->method('send')->willReturn($mockCompletePaymentResponse);
        }

        //--------------------------------------------------------------------------------------------------------------
        // Build the gateway

        $stubGateway = $this->getMockBuilder(TestOffsiteGateway::class)
            ->onlyMethods([
                'getName',
                $this->omnipayMethod,
                $this->omnipayCompleteMethod
            ])->getMock();

        $stubGateway->expects($sendMustFail ? $this->any() : $this->once())
            ->method($this->omnipayMethod)
            ->willReturn($mockPaymentRequest);

        $stubGateway
            ->method($this->omnipayCompleteMethod)
            ->willReturn($mockCompletePaymentRequest);

        return $stubGateway;
    }

    /**
     * @return MockObject&AbstractRequest
     */
    protected function stubRequest()
    {
        $request = $this->getMockBuilder(AbstractRequest::class)
            ->setConstructorArgs([
                $this->createStub(ClientInterface::class),
                SymfonyRequest::create('/'),
            ])
            ->onlyMethods(['send', 'sendData', 'getData'])
            ->getMock();
        $response = $this->createMock(AbstractResponse::class);
        $response->method('isSuccessful')->willReturn(true);
        $request->method('send')->willReturn($response);
        return $request;
    }
}
