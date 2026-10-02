<?php

declare(strict_types=1);

namespace SilverStripe\Omnipay\Tests;

use SilverStripe\Omnipay\Exception\ServiceException;
use Omnipay\Common\Message\AbstractResponse;
use Symfony\Component\HttpFoundation\Response;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Omnipay\Service\ServiceResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Control\HTTPResponse;

class ServiceResponseTest extends FunctionalTest
{
    protected Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->payment = Payment::create()->init("Dummy", 123, "EUR");
    }

    public function testDefaultState(): void
    {
        $serviceResponse = new ServiceResponse($this->payment);

        $this->assertFalse($serviceResponse->isError());
        $this->assertFalse($serviceResponse->isRedirect());
        $this->assertFalse($serviceResponse->isNotification());
        $this->assertFalse($serviceResponse->isAwaitingNotification());
        $this->assertFalse($serviceResponse->isCancelled());

        $this->assertNull($serviceResponse->getOmnipayResponse());
        $this->assertNull($serviceResponse->getHttpResponse());

        $defaultHttpResponse = $serviceResponse->redirectOrRespond();
        $this->assertEquals(200, $defaultHttpResponse->getStatusCode());
        $this->assertEquals("OK", $defaultHttpResponse->getBody());

        $this->assertEquals($serviceResponse->getPayment(), $this->payment);
    }

    public function testFlags(): void
    {
        // pass multiple flags to constructor
        $serviceResponse = new ServiceResponse(
            $this->payment,
            ServiceResponse::SERVICE_ERROR,
            ServiceResponse::SERVICE_NOTIFICATION,
            ServiceResponse::SERVICE_PENDING
        );

        $this->assertTrue($serviceResponse->isError());
        $this->assertTrue($serviceResponse->isNotification());
        $this->assertTrue($serviceResponse->isAwaitingNotification());
        $this->assertFalse($serviceResponse->isCancelled());

        // remove the ERROR flag
        $serviceResponse->removeFlag(ServiceResponse::SERVICE_ERROR);
        $this->assertFalse($serviceResponse->isError());
        $this->assertTrue($serviceResponse->isNotification());
        $this->assertTrue($serviceResponse->isAwaitingNotification());
        $this->assertFalse($serviceResponse->isCancelled());

        // remove multiple flags at once
        $serviceResponse->removeFlag(ServiceResponse::SERVICE_NOTIFICATION | ServiceResponse::SERVICE_PENDING);
        $this->assertFalse($serviceResponse->isError());
        $this->assertFalse($serviceResponse->isNotification());
        $this->assertFalse($serviceResponse->isAwaitingNotification());
        $this->assertFalse($serviceResponse->isCancelled());

        // test adding a flag
        $serviceResponse->addFlag(ServiceResponse::SERVICE_PENDING);
        $this->assertFalse($serviceResponse->isError());
        $this->assertFalse($serviceResponse->isNotification());
        $this->assertTrue($serviceResponse->isAwaitingNotification());
        $this->assertFalse($serviceResponse->isCancelled());

        // test adding multiple flag
        $serviceResponse->addFlag(ServiceResponse::SERVICE_ERROR | ServiceResponse::SERVICE_CANCELLED);
        $this->assertTrue($serviceResponse->isError());
        $this->assertFalse($serviceResponse->isNotification());
        $this->assertTrue($serviceResponse->isAwaitingNotification());
        $this->assertTrue($serviceResponse->isCancelled());

        // test for multiple flags
        $this->assertTrue($serviceResponse->hasFlag(
            ServiceResponse::SERVICE_ERROR | ServiceResponse::SERVICE_PENDING | ServiceResponse::SERVICE_CANCELLED
        ));

        // returns true if at least one flag doesn't match
        $this->assertFalse($serviceResponse->hasFlag(
            ServiceResponse::SERVICE_ERROR | ServiceResponse::SERVICE_NOTIFICATION
        ));

        $this->assertFalse($serviceResponse->hasFlag(ServiceResponse::SERVICE_NOTIFICATION));
    }

    public function testInvalidAddFlag(): void
    {
        $serviceResponse = new ServiceResponse($this->payment);
        $this->expectException('\InvalidArgumentException');
        $serviceResponse->addFlag("Test");
    }

    public function testInvalidHasFlag(): void
    {
        $serviceResponse = new ServiceResponse($this->payment);
        $this->expectException('\InvalidArgumentException');
        $serviceResponse->hasFlag("Test");
    }

    public function testInvalidRemoveFlag(): void
    {
        $serviceResponse = new ServiceResponse($this->payment);
        $this->expectException('\InvalidArgumentException');
        $serviceResponse->removeFlag("Test");
    }

    public function testResponse(): void
    {
        $serviceResponse = new ServiceResponse($this->payment);

        $serviceResponse->setTargetUrl('/my/target/url');

        $httpResponse = $serviceResponse->redirectOrRespond();
        $this->assertStringEndsWith('/my/target/url', $httpResponse->getHeader('Location'));
        $this->assertEquals(302, $httpResponse->getStatusCode());

        // explicitly set a response
        $serviceResponse->setHttpResponse(HTTPResponse::create('Body', 200));

        // response should take precedence before redirect defined through target URL
        $httpResponse = $serviceResponse->redirectOrRespond();
        $this->assertEquals('Body', $httpResponse->getBody());
        $this->assertEquals(200, $httpResponse->getStatusCode());
    }

    public function testRedirectResponse(): void
    {
        $serviceResponse = new ServiceResponse($this->payment);
        $serviceResponse->setTargetUrl('/my/target/url');

        $mockPurchaseResponse = $this->createMock(AbstractResponse::class);

        $mockPurchaseResponse
            ->method('isRedirect')->willReturn(true);

        $mockPurchaseResponse
            ->method('getRedirectResponse')
            ->willReturn(new RedirectResponse('https://gateway.tld/endpoint'));

        // Assign an omnipay redirect response
        $serviceResponse->setOmnipayResponse($mockPurchaseResponse);

        // Should be marked as redirect now
        $this->assertTrue($serviceResponse->isRedirect());
        // the target URL should have changed
        $this->assertEquals('https://gateway.tld/endpoint', $serviceResponse->getTargetUrl());

        // explicitly set a response
        $serviceResponse->setHttpResponse(HTTPResponse::create('Body', 200));

        // redirecting should always return a redirect, EVEN when the http response was set!
        $httpResponse = $serviceResponse->redirectOrRespond();
        $this->assertEquals('https://gateway.tld/endpoint', $httpResponse->getHeader('Location'));
        $this->assertEquals(302, $httpResponse->getStatusCode());

        // trying to set the URL now should trigger an exception
        $this->expectException(ServiceException::class);
        $serviceResponse->setTargetUrl('/my/endpoint');
    }

    // Omnipay can also return a response that contains a self-submitting form
    public function testPostRedirectResponse(): void
    {
        $serviceResponse = new ServiceResponse($this->payment);
        $serviceResponse->setTargetUrl('/my/target/url');

        $mockPurchaseResponse = $this->createMock(AbstractResponse::class);

        $mockPurchaseResponse
            ->method('isRedirect')->willReturn(true);

        $htmlResponse = new Response('SelfSubmittingForm HTML');
        $mockPurchaseResponse
            ->method('getRedirectResponse')
            ->willReturn($htmlResponse);

        // Assign an omnipay redirect response
        $serviceResponse->setOmnipayResponse($mockPurchaseResponse);

        // Should be marked as redirect now
        $this->assertTrue($serviceResponse->isRedirect());
        // the target URL should not have changed
        $this->assertEquals('/my/target/url', $serviceResponse->getTargetUrl());

        // explicitly set a response
        $serviceResponse->setHttpResponse(HTTPResponse::create('Body', 200));

        // redirecting should always return the response from Omnipay, EVEN when the http response was set!
        $httpResponse = $serviceResponse->redirectOrRespond();
        $this->assertEquals(200, $httpResponse->getStatusCode());
        $this->assertEquals('SelfSubmittingForm HTML', $httpResponse->getBody());

        // tryin to set the URL now should trigger an exception
        $this->expectException(ServiceException::class);
        $serviceResponse->setTargetUrl('/my/endpoint');
    }
}
