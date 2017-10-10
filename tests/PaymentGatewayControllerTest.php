<?php

namespace SilverStripe\Omnipay\Tests;

use SilverStripe\Omnipay\Model\Message\CompletePurchaseRequest;
use SilverStripe\Omnipay\Model\Message\PurchasedResponse;
use SilverStripe\Omnipay\Model\Message\PurchaseRedirectResponse;
use SilverStripe\Omnipay\Model\Message\PurchaseRequest;
use SilverStripe\Omnipay\PaymentGatewayController;
use SilverStripe\Control\Director;
use SilverStripe\Omnipay\Model\Payment;

class PaymentGatewayControllerTest extends PaymentTest
{
    protected static $fixture_file = 'PaymentTest.yml';

    public function testReturnUrlGeneration()
    {
        $url = PaymentGatewayController::getEndpointUrl('action', "UniqueHashHere12345");
        $this->assertEquals(
            Director::absoluteURL("paymentendpoint/UniqueHashHere12345/action"),
            $url,
            "generated url"
        );
    }

    public function testCompleteEndpoint()
    {
        $this->setMockHttpResponse(
            'paymentexpress/tests/Mock/PxPayCompletePurchaseSuccess.txt'
        );
        //mock the 'result' get variable into the current request
        $this->getHttpRequest()->query->replace(array('result' => 'abc123'));
        //mimic a redirect or request from offsite gateway
        $response = $this->get("paymentendpoint/UNIQUEHASH23q5123tqasdf/complete");
        //redirect works
        $headers = $response->getHeaders();
        $this->assertStringEndsWith(
            '/shop/complete',
            $headers['Location']
        );
        $payment = Payment::get()
                        ->filter('Identifier', 'UNIQUEHASH23q5123tqasdf')
                        ->first();
        $this->assertDOSContains(array(
            array('ClassName' => PurchaseRequest::class),
            array('ClassName' => PurchaseRedirectResponse::class),
            array('ClassName' => CompletePurchaseRequest::class),
            array('ClassName' => PurchasedResponse::class)
        ), $payment->Messages());
    }

    public function testNotifyEndpoint()
    {
        $this->setMockHttpResponse(
            'paymentexpress/tests/Mock/PxPayCompletePurchaseSuccess.txt'
        );
        //mock the 'result' get variable into the current request
        $this->getHttpRequest()->query->replace(array('result' => 'abc123'));
        //mimic a redirect or request from offsite gateway
        $response = $this->get("paymentendpoint/UNIQUEHASH23q5123tqasdf/notify");
        //redirect works
        $this->assertNull($response->getHeader('Location'));
        $payment = Payment::get()
                        ->filter('Identifier', 'UNIQUEHASH23q5123tqasdf')
                        ->first();
        $this->assertDOSContains(array(
            array('ClassName' => PurchaseRequest::class),
            array('ClassName' => PurchaseRedirectResponse::class),
            array('ClassName' => CompletePurchaseRequest::class),
            array('ClassName' => PurchasedResponse::class)
        ), $payment->Messages());
    }

    public function testCancelEndpoint()
    {
        // mimic a redirect or request from offsite gateway. The user cancelled the payment
        $response = $this->get("paymentendpoint/UNIQUEHASH23q5123tqasdf/cancel");

        // Should redirect to the cancel/failure url which is being loaded from the fixture
        $headers = $response->getHeaders();
        $this->assertStringEndsWith(
            '/shop/incomplete',
            $headers['Location']
        );

        $payment = Payment::get()
            ->filter('Identifier', 'UNIQUEHASH23q5123tqasdf')
            ->first();

        $this->assertDOSContains(array(
            array('ClassName' => PurchaseRequest::class),
            array('ClassName' => PurchaseRedirectResponse::class)
        ), $payment->Messages());

        $this->assertEquals($payment->Status, 'Void', 'Payment should be void');
    }

    public function testInvalidAction()
    {
        // Try to access a valid payment, but bad action
        $response = $this->get("paymentendpoint/UNIQUEHASH23q5123tqasdf/bogus");

        $this->assertEquals($response->getStatusCode(), 404);
    }

    public function testBadReturnURLs()
    {
        $response = $this->get("paymentendpoint/ASDFHSADFunknonwhash/complete/c2hvcC9jb2");
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testInvalidStatus()
    {
        // try to complete a payment that has status "Created"
        $response = $this->get("paymentendpoint/ce3a0b03349078d8e85d1de8ded3f0/complete");
        $this->assertEquals($response->getStatusCode(), 403);
    }
}
