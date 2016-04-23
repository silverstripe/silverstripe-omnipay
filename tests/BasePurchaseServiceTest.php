<?php

use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Abstract base-class that implements common tests for "authorize" and "purchase".
 * Uses variables that have to be configured to the expected values in the subclass.
 */
abstract class BasePurchaseServiceTest extends PaymentTest
{
    /** @var string The expected payment complete status, "Captured" or "Authorized" */
    protected $completeStatus;
    /** @var string The expected payment pending status, "PendingPurchase" or "PendingAuthorization" */
    protected $pendingStatus;

    /** @var string The omnipay method to call, "purchase" or "authorize" */
    protected $omnipayMethod;
    /** @var string The omnipay method to call to complete, "completePurchase" or "completeAuthorize" */
    protected $omnipayCompleteMethod;

    /** @var array an array of the expected messages with a successful onsite payment */
    protected $onsiteSuccessMessages;
    /** @var array an array of the expected messages with a failed onsite payment */
    protected $onsiteFailMessages;
    /** @var array an array of the expected messages with a payment that fails because of bad configuration */
    protected $failMessages;

    /** @var array an array of the expected messages for a successful offsite payment */
    protected $offsiteSuccessMessages;
    /** @var array an array of the expected messages for a failed offsite payment */
    protected $offsiteFailMessages;
    /** @var  string name of the failure message class */
    protected $failureMessageClass;

    /** @var string The ID of the payment (@see payment.yml) */
    protected $paymentId;

    /**
     * Create a payment service instance
     * @param Payment $payment
     * @return \SilverStripe\Omnipay\Service\PaymentService
     */
    abstract protected function getService(Payment $payment);

    public function testDummyOnSitePayment()
    {
        $payment = $this->payment;

        $service = $this->getService($payment);
        $response = $service->initiate(array(
            'firstName' => 'joe',
            'lastName' => 'bloggs',
            'number' => '4242424242424242', //this creditcard will succeed
            'expiryMonth' => '5',
            'expiryYear' => date("Y", strtotime("+1 year"))
        ));

        $this->assertEquals($this->completeStatus, $payment->Status, "is the status updated");
        $this->assertEquals(1222, $payment->Amount);
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

        $this->assertEquals(1222, $payment->Amount);
        $this->assertEquals("GBP", $payment->Currency);
        $this->assertEquals("Dummy", $payment->Gateway);

        //check messaging
        $this->assertDOSContains($this->onsiteSuccessMessages, $payment->Messages());
    }

    public function testFailedDummyOnSitePayment()
    {
        $payment = $this->payment;
        $service = $this->getService($payment);
        $response = $service->initiate(array(
            'firstName' => 'joe',
            'lastName' => 'bloggs',
            'number' => '4111111111111111',  //this creditcard will decline
            'expiryMonth' => '5',
            'expiryYear' => date("Y", strtotime("+1 year"))
        ));
        $this->assertEquals("Created", $payment->Status, "is the status has not been updated");
        $this->assertEquals(1222, $payment->Amount);
        $this->assertEquals("GBP", $payment->Currency);
        $this->assertFalse($response->getOmnipayResponse()->isSuccessful());
        $this->assertTrue($response->isError());
        $this->assertFalse($response->isRedirect());

        //check messaging
        $this->assertDOSContains($this->onsiteFailMessages, $payment->Messages());
    }

    public function testOnSitePayment()
    {
        $payment = $this->payment->setGateway('PaymentExpress_PxPost');
        $service = $this->getService($payment);
        $this->setMockHttpResponse('paymentexpress/tests/Mock/PxPostPurchaseSuccess.txt');//add success mock response from file
        $response = $service->initiate(array(
            'firstName' => 'joe',
            'lastName' => 'bloggs',
            'number' => '4242424242424242', //this creditcard will succeed
            'expiryMonth' => '5',
            'expiryYear' => date("Y", strtotime("+1 year"))
        ));
        $this->assertTrue($response->getOmnipayResponse()->isSuccessful());
        $this->assertFalse($response->isRedirect());
        $this->assertFalse($response->isError());
        $this->assertSame($this->completeStatus, $payment->Status);

        //check messaging
        $this->assertDOSContains($this->onsiteSuccessMessages, $payment->Messages());
    }

    public function testInvalidOnsitePayment()
    {
        $payment = $this->payment->setGateway("PaymentExpress_PxPost");
        $service = $this->getService($payment);
        //pass no card details nothing
        $response = $service->initiate(array());

        //check messaging
        $this->assertFalse($response->isRedirect());
        $this->assertTrue($response->isError());
        $this->assertDOSContains($this->failMessages, $payment->Messages());
    }

    public function testFailedOnSitePayment()
    {
        $payment = $this->payment->setGateway('PaymentExpress_PxPost');
        $service = $this->getService($payment);
        $this->setMockHttpResponse('paymentexpress/tests/Mock/PxPostPurchaseFailure.txt');//add success mock response from file
        $response = $service->initiate(array(
            'number' => '4111111111111111', //this creditcard will decline
            'expiryMonth' => '5',
            'expiryYear' => date("Y", strtotime("+1 year"))
        ));
        $this->assertFalse($response->getOmnipayResponse()->isSuccessful()); // capturing/authorization wasn't successful
        $this->assertFalse($response->isRedirect());
        $this->assertTrue($response->isError());
        $this->assertSame("Created", $payment->Status);

        //check messaging
        $this->assertDOSContains($this->onsiteFailMessages, $payment->Messages());
    }

    public function testOffSitePayment()
    {
        $payment = $this->payment->setGateway('PaymentExpress_PxPay');
        $service = $this->getService($payment);
        $this->setMockHttpResponse('paymentexpress/tests/Mock/PxPayPurchaseSuccess.txt');//add success mock response from file
        $response = $service->initiate();
        $this->assertFalse($response->getOmnipayResponse()->isSuccessful()); // capturing/authorization wasn't successful
        $this->assertTrue($response->isRedirect());
        $this->assertFalse($response->isError()); // this should not be considered to be an error

        $this->assertSame(
            'https://sec.paymentexpress.com/pxpay/pxpay.aspx?userid=Developer&request=v5H7JrBTzH-4Whs__1iQnz4RGSb9qxRKNR4kIuDP8kIkQzIDiIob9GTIjw_9q_AdRiR47ViWGVx40uRMu52yz2mijT39YtGeO7cZWrL5rfnx0Mc4DltIHRnIUxy1EO1srkNpxaU8fT8_1xMMRmLa-8Fd9bT8Oq0BaWMxMquYa1hDNwvoGs1SJQOAJvyyKACvvwsbMCC2qJVyN0rlvwUoMtx6gGhvmk7ucEsPc_Cyr5kNl3qURnrLKxINnS0trdpU4kXPKOlmT6VacjzT1zuj_DnrsWAPFSFq-hGsow6GpKKciQ0V0aFbAqECN8rl_c-aZWFFy0gkfjnUM4qp6foS0KMopJlPzGAgMjV6qZ0WfleOT64c3E-FRLMP5V_-mILs8a',
            $response->getTargetUrl());
        // Status should be set to pending
        $this->assertSame($this->pendingStatus, $payment->Status);

        //... user would normally be redirected to external gateway at this point ...

        //mock complete Payment response
        $this->setMockHttpResponse('paymentexpress/tests/Mock/PxPayCompletePurchaseSuccess.txt');
        //mock the 'result' get variable into the current request
        $this->getHttpRequest()->query->replace(array('result' => 'abc123'));
        $response = $service->complete();
        $this->assertTrue($response->getOmnipayResponse()->isSuccessful());
        $this->assertSame($this->completeStatus, $payment->Status);
        $this->assertFalse($response->isError());
        // payment should get the transaction reference from Omnipay assigned
        $reference = $response->getOmnipayResponse()->getTransactionReference();
        $this->assertNotNull($reference);
        $this->assertEquals($payment->TransactionReference, $reference);

        //check messaging
        $this->assertDOSContains($this->offsiteSuccessMessages, $payment->Messages());
    }

    public function testFailedOffSitePayment()
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
        $this->assertDOSContains($this->onsiteFailMessages, $payment->Messages());
    }

    public function testFailedOffSiteCompletePayment()
    {
        $this->setMockHttpResponse(
            'paymentexpress/tests/Mock/PxPayCompletePurchaseFailure.txt'
        );
        //mock the 'result' get variable into the current request
        $this->getHttpRequest()->query->replace(array('result' => 'abc123'));
        //mimic a redirect or request from offsite gateway
        $response = $this->get("paymentendpoint/$this->paymentId/complete");
        //redirect works
        $headers = $response->getHeaders();
        $this->assertEquals(
            Director::baseURL() . "shop/incomplete",
            $headers['Location'],
            "redirected to shop/incomplete"
        );
        $payment = Payment::get()
            ->filter('Identifier', $this->paymentId)
            ->first();
        $this->assertDOSContains($this->offsiteFailMessages, $payment->Messages());
    }

    /**
     * @expectedException \Omnipay\Common\Exception\RuntimeException
     */
    public function testNonExistantGateway()
    {
        //exception when trying to run functions that require a gateway
        $payment = $this->payment;
        $service = $this->getService(
            $payment->init("FantasyGateway", 100, "NZD")->setSuccessUrl("complete")
        );

        // Will throw an exception since the gateway doesn't exist
        $service->initiate();
    }

    /**
     * @expectedException \SilverStripe\Omnipay\Exception\InvalidStateException
     */
    public function testPaymentInvalidStatus()
    {
        $payment = $this->payment;
        $payment->Status = 'Void';
        $service = $this->getService($payment);

        $service->initiate();
    }

    /**
     * @expectedException \SilverStripe\Omnipay\Exception\InvalidStateException
     */
    public function testCompletePaymentInvalidStatus()
    {
        $payment = $this->payment;
        $payment->Status = 'Void';
        $service = $this->getService($payment);

        $service->complete();
    }

    /**
     * @expectedException \SilverStripe\Omnipay\Exception\InvalidConfigurationException
     */
    public function testGatewayDoesntSupportMethod()
    {
        // Build the dummy gateway
        $stubGateway = $this->getMockBuilder('Omnipay\Common\AbstractGateway')
            ->setMethods(array('getName'))
            ->getMock();

        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), 'Omnipay\Common\GatewayFactory');

        $this->payment->Status = 'Created';
        $service = $this->getService($this->payment);
        // this should throw an exception, because the gateway doesn't support the payment method
        $service->initiate();
    }

    /**
     * @expectedException \SilverStripe\Omnipay\Exception\InvalidConfigurationException
     */
    public function testGatewayDoesntSupportCompleteMethod()
    {
        // Build the dummy gateway
        $stubGateway = $this->getMockBuilder('Omnipay\Common\AbstractGateway')
            ->setMethods(array('getName'))
            ->getMock();

        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), 'Omnipay\Common\GatewayFactory');

        $this->payment->Status = $this->pendingStatus;
        $service = $this->getService($this->payment);
        // this should throw an exception, because the gateway doesn't support the complete method
        $service->complete();
    }

    public function testGatewayCompleteMethodFailure()
    {
        // build a stub gateway with the given endpoint
        $stubGateway = $this->buildPaymentGatewayStub('https://gateway.tld/endpoint', function () {
            return true;
        }, true);

        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), 'Omnipay\Common\GatewayFactory');

        $this->payment->Status = $this->pendingStatus;
        $service = $this->getService($this->payment);

        // this should return an error response
        $serviceResponse = $service->complete();

        $this->assertTrue($serviceResponse->isError());
        $this->assertNull($serviceResponse->getOmnipayResponse());
        $this->assertDOSContains(array(
            array(
                'ClassName' => $this->failureMessageClass,
                'Message' => 'Mock Exception'
            )
        ), $this->payment->Messages());
    }


    public function testTokenGateway()
    {
        Config::inst()->update('GatewayInfo', 'PaymentExpress_PxPost', array(
            'token_key' => 'token'
        ));
        $stubGateway = $this->getMockBuilder('Omnipay\Common\AbstractGateway')
            ->setMethods(array($this->omnipayMethod, 'getName'))
            ->getMock();

        $stubGateway->expects($this->once())
            ->method($this->omnipayMethod)
            ->with(
                $this->logicalAnd(
                    $this->arrayHasKey('token'),
                    $this->contains('ABC123'),
                    $this->logicalNot($this->arrayHasKey('card'))
                )
            )
            ->will(
                $this->returnValue($this->stubRequest())
            );

        $payment = $this->payment->setGateway('PaymentExpress_PxPost');

        $service = $this->getService($payment);
        $service->setGatewayFactory($this->stubGatewayFactory($stubGateway));

        $service->initiate(array('token' => 'ABC123'));
    }

    public function testTokenGatewayWithAlternateKey()
    {
        Config::inst()->update('GatewayInfo', 'PaymentExpress_PxPost', array(
            'token_key' => 'my_token'
        ));
        $stubGateway = $this->getMockBuilder('Omnipay\Common\AbstractGateway')
            ->setMethods(array($this->omnipayMethod, 'getName'))
            ->getMock();

        $stubGateway->expects($this->once())
            ->method($this->omnipayMethod)
            ->with(
                $this->logicalAnd(
                    $this->arrayHasKey('token'), // my_token should get normalized to this
                    $this->contains('ABC123'),
                    $this->logicalNot($this->arrayHasKey('card'))
                )
            )
            ->will(
                $this->returnValue($this->stubRequest())
            );

        $payment = $this->payment->setGateway('PaymentExpress_PxPost');

        $service = $this->getService($payment);
        $service->setGatewayFactory($this->stubGatewayFactory($stubGateway));

        $service->initiate(array('my_token' => 'ABC123'));
    }

    public function testAsyncPaymentConfirmation()
    {
        Config::inst()->update('GatewayInfo', 'PaymentExpress_PxPay', array(
            'use_async_notification' => true
        ));

        // build a stub gateway with the given endpoint
        $isNotification = false;
        $stubGateway = $this->buildPaymentGatewayStub('https://gateway.tld/endpoint', function () use (&$isNotification) {
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
        $this->assertEquals($serviceResponse->getTargetUrl(), 'https://gateway.tld/endpoint');
        // Payment should be pending
        $this->assertEquals($payment->Status, $this->pendingStatus);

        $serviceResponse = $service->complete(array(), $isNotification);

        // since the confirmation will come in asynchronously, the gateway doesn't report success when coming back
        $this->assertFalse($serviceResponse->getOmnipayResponse()->isSuccessful(), 'Gateway will not return success');
        // Our application considers that fact and doesn't mark the service call as an error!
        $this->assertFalse($serviceResponse->isError());
        // We should get redirected to the success page now
        $this->assertEquals($serviceResponse->getTargetUrl(), 'my/return/url');
        // Payment status should still be pending
        $this->assertEquals($payment->Status, $this->pendingStatus);


        // simulate an incoming notification
        $isNotification = true;

        $serviceResponse = $service->complete(array(), $isNotification);

        // the response from the gateway should now be successful
        $this->assertTrue($serviceResponse->getOmnipayResponse()->isSuccessful(), 'Response should be successful');
        // Should not be an error
        $this->assertFalse($serviceResponse->isError());
        // We should get an HTTP response with "OK"
        $httpResponse = $serviceResponse->redirectOrRespond();
        $this->assertEquals($httpResponse->getBody(), 'OK');
        $this->assertEquals($httpResponse->getStatusCode(), 200);
        // Payment status should be authorized or captured now (completed)
        $this->assertEquals($payment->Status, $this->completeStatus);
    }

    // Test an async response that comes in before the user returns from the offsite form
    public function testAsyncPaymentConfirmationIncomingFirst()
    {
        Config::inst()->update('GatewayInfo', 'PaymentExpress_PxPay', array(
            'use_async_notification' => true
        ));

        // build a stub gateway with the given endpoint
        $isNotification = true;
        $stubGateway = $this->buildPaymentGatewayStub('https://gateway.tld/endpoint', function () use (&$isNotification) {
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
        $serviceResponse = $service->complete(array(), $isNotification);

        // since we're getting the async notification now, payment should be successful
        $this->assertTrue($serviceResponse->getOmnipayResponse()->isSuccessful(), 'Response should be successful');
        // Should not be an error
        $this->assertFalse($serviceResponse->isError());
        // We should get an HTTP response with "OK"
        $httpResponse = $serviceResponse->redirectOrRespond();
        $this->assertEquals($httpResponse->getBody(), 'OK');
        $this->assertEquals($httpResponse->getStatusCode(), 200);
        // Payment status should be captured or authorized (completed)
        $this->assertEquals($payment->Status, $this->completeStatus);

        // Now the user comes back from the offsite payment form
        $isNotification = false;
        $serviceResponse = $service->complete(array(), $isNotification);

        // We won't get an error, our payment is already complete
        $this->assertFalse($serviceResponse->isError());
        // There's no omnipay response since we no longer need to bother with omnipay at this point
        $this->assertNull($serviceResponse->getOmnipayResponse(), 'No omnipay response, payment already completed');
        // We should get redirected to the success page now
        $this->assertEquals($serviceResponse->getTargetUrl(), 'my/return/url');
        // Payment status should still be captured or authorized
        $this->assertEquals($payment->Status, $this->completeStatus);
    }

    // Test an async response that comes in before the user returns from the offsite form.
    // Test via PaymentGatewayController
    public function testPaymentGatewayControllerConfirmationIncomingFirst()
    {
        Config::inst()->update('GatewayInfo', 'PaymentExpress_PxPay', array(
            'use_async_notification' => true
        ));

        // build a stub gateway with the given endpoint
        $isNotification = true;
        $stubGateway = $this->buildPaymentGatewayStub('https://gateway.tld/endpoint', function () use (&$isNotification) {
            return $isNotification;
        });
        $payment = $this->payment->setGateway('PaymentExpress_PxPay');
        $payment->setFailureUrl('my/cancel/url')->setSuccessUrl('my/return/url');
        $service = $this->getService($payment);

        // register our mock gateway factory as injection
        Injector::inst()->registerService($this->stubGatewayFactory($stubGateway), 'Omnipay\Common\GatewayFactory');

        $serviceResponse = $service->initiate();

        // we should get a redirect
        $this->assertTrue($serviceResponse->isRedirect());
        // Payment should be pending
        $this->assertEquals($payment->Status, $this->pendingStatus);

        // Notification comes in first!
        $httpResponse = $this->get('paymentendpoint/'. $payment->Identifier .'/notify');

        $this->assertEquals($httpResponse->getBody(), 'OK');
        $this->assertEquals($httpResponse->getStatusCode(), 200);

        // reload payment from DB
        $payment = Payment::get()->byID($payment->ID);
        // Payment status should be captured or authorized (completed)
        $this->assertEquals($payment->Status, $this->completeStatus);

        // Now the user comes back from the offsite payment form
        $httpResponse = $this->get('paymentendpoint/'. $payment->Identifier .'/complete');

        // we should be redirected to the success page
        $this->assertEquals($httpResponse->getHeader('Location'), BASE_URL . '/my/return/url');
        $this->assertEquals($httpResponse->getStatusCode(), 302);

        // reload payment from DB
        $payment = Payment::get()->byID($payment->ID);
        // Payment status should still be captured or authorized
        $this->assertEquals($payment->Status, $this->completeStatus);
    }

    protected function buildPaymentGatewayStub($endpoint, Closure $successFunc, $sendMustFail = false)
    {
        //--------------------------------------------------------------------------------------------------------------
        // Payment request and response

        $mockPaymentResponse = $this->getMockBuilder('Omnipay\PaymentExpress\Message\Response')
            ->disableOriginalConstructor()->getMock();

        $mockPaymentResponse->expects($this->any())
            ->method('isRedirect')->will($this->returnValue(true));

        $mockPaymentResponse->expects($this->any())
            ->method('getRedirectResponse')
            ->will($this->returnValue(RedirectResponse::create($endpoint)));

        $mockPaymentRequest = $this->getMockBuilder('Omnipay\PaymentExpress\Message\PxPayPurchaseRequest')
            ->disableOriginalConstructor()->getMock();

        if ($sendMustFail) {
            $mockPaymentRequest->expects($this->any())->method('send')->will($this->throwException(
                new \Omnipay\Common\Exception\RuntimeException('Mock Exception')
            ));
        } else {
            $mockPaymentRequest->expects($this->any())->method('send')->will($this->returnValue($mockPaymentResponse));
        }

        //--------------------------------------------------------------------------------------------------------------
        // Complete Payment request and response

        $mockCompletePaymentResponse = $this->getMockBuilder('Omnipay\PaymentExpress\Message\Response')
            ->disableOriginalConstructor()->getMock();

        // not successful, since we're waiting for async callback from the gateway
        $mockCompletePaymentResponse->expects($this->any())
            ->method('isSuccessful')->will($this->returnCallback($successFunc));

        $mockCompletePaymentRequest = $this->getMockBuilder('Omnipay\PaymentExpress\Message\PxPayCompleteAuthorizeRequest')
            ->disableOriginalConstructor()->getMock();

        if ($sendMustFail) {
            $mockCompletePaymentRequest->expects($this->any())->method('send')->will($this->throwException(
                new \Omnipay\Common\Exception\RuntimeException('Mock Exception')
            ));
        } else {
            $mockCompletePaymentRequest->expects($this->any())
                ->method('send')->will($this->returnValue($mockCompletePaymentResponse));
        }

        //--------------------------------------------------------------------------------------------------------------
        // Build the gateway

        $stubGateway = $this->getMockBuilder('Omnipay\Common\AbstractGateway')
            ->setMethods(array($this->omnipayMethod, $this->omnipayCompleteMethod, 'getName'))
            ->getMock();

        $stubGateway->expects($sendMustFail ? $this->any() : $this->once())
            ->method($this->omnipayMethod)
            ->will($this->returnValue($mockPaymentRequest));

        $stubGateway->expects($this->any())
            ->method($this->omnipayCompleteMethod)
            ->will($this->returnValue($mockCompletePaymentRequest));

        return $stubGateway;
    }

    /**
     * @return PHPUnit_Framework_MockObject_MockObject|Omnipay\Common\Message\AbstractRequest
     */
    protected function stubRequest()
    {
        $request = $this->getMockBuilder('Omnipay\Common\Message\AbstractRequest')
            ->disableOriginalConstructor()
            ->getMock();
        $response = $this->getMockBuilder('Omnipay\Common\Message\AbstractResponse')
            ->disableOriginalConstructor()
            ->getMock();
        $response->expects($this->any())->method('isSuccessful')->will($this->returnValue(true));
        $request->expects($this->any())->method('send')->will($this->returnValue($response));
        return $request;
    }
}
