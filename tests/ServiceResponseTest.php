<?php

use SilverStripe\Omnipay\Service\ServiceResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ServiceResponseTest extends SapphireTest
{
    /** @var Payment */
    protected $payment;

    public function setUp()
    {
        parent::setUp();

        $this->payment = Payment::create()->init("Dummy", 123, "EUR");
    }

    public function testDefaultState()
    {
        $response = new ServiceResponse($this->payment);

        $this->assertFalse($response->isError());
        $this->assertFalse($response->isRedirect());
        $this->assertFalse($response->isNotification());
        $this->assertFalse($response->isAwaitingNotification());
        $this->assertFalse($response->isCancelled());

        $this->assertNull($response->getOmnipayResponse());
        $this->assertNull($response->getHttpResponse());

        $defaultHttpResponse = $response->redirectOrRespond();
        $this->assertEquals($defaultHttpResponse->getStatusCode(), 200);
        $this->assertEquals($defaultHttpResponse->getBody(), "OK");

        $this->assertEquals($response->getPayment(), $this->payment);
    }

    public function testFlags()
    {
        // pass multiple flags to constructor
        $response = new ServiceResponse(
            $this->payment,
            ServiceResponse::SERVICE_ERROR,
            ServiceResponse::SERVICE_NOTIFICATION,
            ServiceResponse::SERVICE_PENDING
        );

        $this->assertTrue($response->isError());
        $this->assertTrue($response->isNotification());
        $this->assertTrue($response->isAwaitingNotification());
        $this->assertFalse($response->isCancelled());

        // remove the ERROR flag
        $response->removeFlag(ServiceResponse::SERVICE_ERROR);
        $this->assertFalse($response->isError());
        $this->assertTrue($response->isNotification());
        $this->assertTrue($response->isAwaitingNotification());
        $this->assertFalse($response->isCancelled());

        // remove multiple flags at once
        $response->removeFlag(ServiceResponse::SERVICE_NOTIFICATION | ServiceResponse::SERVICE_PENDING);
        $this->assertFalse($response->isError());
        $this->assertFalse($response->isNotification());
        $this->assertFalse($response->isAwaitingNotification());
        $this->assertFalse($response->isCancelled());

        // test adding a flag
        $response->addFlag(ServiceResponse::SERVICE_PENDING);
        $this->assertFalse($response->isError());
        $this->assertFalse($response->isNotification());
        $this->assertTrue($response->isAwaitingNotification());
        $this->assertFalse($response->isCancelled());

        // test adding multiple flag
        $response->addFlag(ServiceResponse::SERVICE_ERROR | ServiceResponse::SERVICE_CANCELLED);
        $this->assertTrue($response->isError());
        $this->assertFalse($response->isNotification());
        $this->assertTrue($response->isAwaitingNotification());
        $this->assertTrue($response->isCancelled());

        // test for multiple flags
        $this->assertTrue($response->hasFlag(
            ServiceResponse::SERVICE_ERROR | ServiceResponse::SERVICE_PENDING | ServiceResponse::SERVICE_CANCELLED
        ));

        // returns true if at least one flag doesn't match
        $this->assertFalse($response->hasFlag(
            ServiceResponse::SERVICE_ERROR | ServiceResponse::SERVICE_NOTIFICATION
        ));

        $this->assertFalse($response->hasFlag(ServiceResponse::SERVICE_NOTIFICATION));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidAddFlag()
    {
        $response = new ServiceResponse($this->payment);
        $response->addFlag("Test");
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidHasFlag()
    {
        $response = new ServiceResponse($this->payment);
        $response->hasFlag("Test");
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidRemoveFlag()
    {
        $response = new ServiceResponse($this->payment);
        $response->removeFlag("Test");
    }

    public function testResponse()
    {
        $response = new ServiceResponse($this->payment);

        $response->setTargetUrl('/my/target/url');

        $httpResponse = $response->redirectOrRespond();
        $this->assertEquals($httpResponse->getHeader('Location'), '/my/target/url');
        $this->assertEquals($httpResponse->getStatusCode(), 302);

        // explicitly set a response
        $response->setHttpResponse(new SS_HTTPResponse('Body', 200));

        // response should take precedence before redirect defined through target URL
        $httpResponse = $response->redirectOrRespond();
        $this->assertEquals($httpResponse->getBody(), 'Body');
        $this->assertEquals($httpResponse->getStatusCode(), 200);
    }

    /**
     * @expectedException \SilverStripe\Omnipay\Exception\ServiceException
     */
    public function testRedirectResponse()
    {
        $response = new ServiceResponse($this->payment);
        $response->setTargetUrl('/my/target/url');

        $mockPurchaseResponse = $this->getMockBuilder('Omnipay\Common\Message\AbstractResponse')
            ->disableOriginalConstructor()->getMock();

        $mockPurchaseResponse->expects($this->any())
            ->method('isRedirect')->will($this->returnValue(true));

        $mockPurchaseResponse->expects($this->any())
            ->method('getRedirectResponse')
            ->will($this->returnValue(RedirectResponse::create('https://gateway.tld/endpoint')));

        // Assign an omnipay redirect response
        $response->setOmnipayResponse($mockPurchaseResponse);

        // Should be marked as redirect now
        $this->assertTrue($response->isRedirect());
        // the target URL should have changed
        $this->assertEquals($response->getTargetUrl(), 'https://gateway.tld/endpoint');

        // explicitly set a response
        $response->setHttpResponse(new SS_HTTPResponse('Body', 200));

        // redirecting should always return a redirect, EVEN when the http response was set!
        $httpResponse = $response->redirectOrRespond();
        $this->assertEquals($httpResponse->getHeader('Location'), 'https://gateway.tld/endpoint');
        $this->assertEquals($httpResponse->getStatusCode(), 302);

        // tryin to set the URL now should trigger an exception
        $response->setTargetUrl('/my/endpoint');
    }

    // Omnipay can also return a response that contains a self-submitting form
    /**
     * @expectedException \SilverStripe\Omnipay\Exception\ServiceException
     */
    public function testPostRedirectResponse()
    {
        $response = new ServiceResponse($this->payment);
        $response->setTargetUrl('/my/target/url');

        $mockPurchaseResponse = $this->getMockBuilder('Omnipay\Common\Message\AbstractResponse')
            ->disableOriginalConstructor()->getMock();

        $mockPurchaseResponse->expects($this->any())
            ->method('isRedirect')->will($this->returnValue(true));

        $htmlResponse = \Symfony\Component\HttpFoundation\Response::create('SelfSubmittingForm HTML');
        $mockPurchaseResponse->expects($this->any())
            ->method('getRedirectResponse')
            ->will($this->returnValue($htmlResponse));

        // Assign an omnipay redirect response
        $response->setOmnipayResponse($mockPurchaseResponse);

        // Should be marked as redirect now
        $this->assertTrue($response->isRedirect());
        // the target URL should not have changed
        $this->assertEquals($response->getTargetUrl(), '/my/target/url');

        // explicitly set a response
        $response->setHttpResponse(new SS_HTTPResponse('Body', 200));

        // redirecting should always return the response from Omnipay, EVEN when the http response was set!
        $httpResponse = $response->redirectOrRespond();
        $this->assertEquals($httpResponse->getStatusCode(), 200);
        $this->assertEquals($httpResponse->getBody(), 'SelfSubmittingForm HTML');

        // tryin to set the URL now should trigger an exception
        $response->setTargetUrl('/my/endpoint');
    }

}
