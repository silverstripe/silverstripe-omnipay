<?php

declare(strict_types=1);

namespace SilverStripe\Omnipay\Tests;

use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\PaymentGatewayController;
use SilverStripe\Omnipay\Service\PurchaseService;
use SilverStripe\Omnipay\Tests\Extensions\PaymentGatewayControllerTestExtension;

class PaymentGatewayControllerTest extends FunctionalTest
{
    use PaymentTestTrait;

    protected static $fixture_file = 'PaymentTest.yml';

    protected $autoFollowRedirection = false;

    public function testReturnUrlGeneration(): void
    {
        $url = PaymentGatewayController::getEndpointUrl('action', "UniqueHashHere12345");
        $this->assertEquals(
            Director::absoluteURL("paymentendpoint/UniqueHashHere12345/action"),
            $url,
            "generated url"
        );
    }

    public function testStaticUrlGeneration(): void
    {
        $url = PaymentGatewayController::getStaticEndpointUrl('Dummy', 'complete');
        $this->assertEquals('', $url);

        Config::modify()->merge(GatewayInfo::class, 'Dummy', ['use_static_route' => true]);
        $url = PaymentGatewayController::getStaticEndpointUrl('Dummy', 'complete');
        $this->assertEquals(Director::absoluteURL("paymentendpoint/gateway/Dummy/complete"), $url);

        $url = PaymentGatewayController::getStaticEndpointUrl('Dummy');
        $this->assertEquals(Director::absoluteURL("paymentendpoint/gateway/Dummy"), $url);
    }

    public function testCompleteEndpoint(): void
    {
        $this->setMockHttpResponse(
            'paymentexpress/tests/Mock/PxPayCompletePurchaseSuccess.txt'
        );
        //mock the 'result' get variable into the current request
        $this->getHttpRequest()->query->replace(['result' => 'abc123']);
        //mimic a redirect or request from offsite gateway
        $httpResponse = $this->get("paymentendpoint/UNIQUEHASH23q5123tqasdf/complete");
        //redirect works
        $this->assertStringEndsWith(
            '/shop/complete',
            $httpResponse->getHeader('Location')
        );
        $payment = Payment::get()
                        ->filter('Identifier', 'UNIQUEHASH23q5123tqasdf')
                        ->first();
        $this->assertInstanceOf(Payment::class, $payment);
        SapphireTest::assertListContains([
            ['Type' => PurchaseService::MESSAGE_PURCHASE_REQUEST],
            ['Type' => PurchaseService::MESSAGE_PURCHASE_REDIRECT_RESPONSE],
            ['Type' => PurchaseService::MESSAGE_COMPLETE_PURCHASE_REQUEST],
            ['Type' => PurchaseService::MESSAGE_PURCHASED_RESPONSE]
        ], $payment->Messages());
    }

    public function testNotifyEndpoint(): void
    {
        $this->setMockHttpResponse(
            'paymentexpress/tests/Mock/PxPayCompletePurchaseSuccess.txt'
        );
        //mock the 'result' get variable into the current request
        $this->getHttpRequest()->query->replace(['result' => 'abc123']);
        //mimic a redirect or request from offsite gateway
        $httpResponse = $this->get("paymentendpoint/UNIQUEHASH23q5123tqasdf/notify");
        //redirect works
        $this->assertNull($httpResponse->getHeader('Location'));
        $payment = Payment::get()
                        ->filter('Identifier', 'UNIQUEHASH23q5123tqasdf')
                        ->first();
        $this->assertInstanceOf(Payment::class, $payment);
        SapphireTest::assertListContains([
            ['Type' => PurchaseService::MESSAGE_PURCHASE_REQUEST],
            ['Type' => PurchaseService::MESSAGE_PURCHASE_REDIRECT_RESPONSE],
            ['Type' => PurchaseService::MESSAGE_COMPLETE_PURCHASE_REQUEST],
            ['Type' => PurchaseService::MESSAGE_PURCHASED_RESPONSE]
        ], $payment->Messages());
    }

    public function testCancelEndpoint(): void
    {
        // mimic a redirect or request from offsite gateway. The user cancelled the payment
        $httpResponse = $this->get("paymentendpoint/UNIQUEHASH23q5123tqasdf/cancel");

        // Should redirect to the cancel/failure url which is being loaded from the fixture
        $this->assertStringEndsWith(
            '/shop/incomplete',
            $httpResponse->getHeader('Location')
        );

        $payment = Payment::get()
            ->filter('Identifier', 'UNIQUEHASH23q5123tqasdf')
            ->first();
        $this->assertInstanceOf(Payment::class, $payment);

        SapphireTest::assertListContains([
            ['Type' => PurchaseService::MESSAGE_PURCHASE_REQUEST],
            ['Type' => PurchaseService::MESSAGE_PURCHASE_REDIRECT_RESPONSE]
        ], $payment->Messages());

        $this->assertEquals('Void', $payment->Status, 'Payment should be void');
    }

    public function testInvalidAction(): void
    {
        // Try to access a valid payment, but bad action
        $httpResponse = $this->get("paymentendpoint/UNIQUEHASH23q5123tqasdf/bogus");

        $this->assertEquals(404, $httpResponse->getStatusCode());
    }

    public function testBadReturnURLs(): void
    {
        $httpResponse = $this->get("paymentendpoint/ASDFHSADFunknonwhash/complete/c2hvcC9jb2");
        $this->assertEquals(404, $httpResponse->getStatusCode());
    }

    public function testInvalidStatus(): void
    {
        // try to complete a payment that has status "Created"
        $httpResponse = $this->get("paymentendpoint/ce3a0b03349078d8e85d1de8ded3f0/complete");
        $this->assertEquals(403, $httpResponse->getStatusCode());
    }

    public function testStaticRoute(): void
    {
        Config::nest();

        $staticUrl = 'paymentendpoint/gateway/PaymentExpress_PxPay/complete?id=UNIQUEHASH23q5123tqasdf';
        $response = $this->get($staticUrl);
        // should return 404, because static route isn't enabled
        $this->assertEquals(404, $response->getStatusCode());

        // Configure gateway to use static route
        Config::modify()->set(GatewayInfo::class, 'PaymentExpress_PxPay', [
            'use_static_route' => true
        ]);

        $this->setMockHttpResponse(
            'paymentexpress/tests/Mock/PxPayCompletePurchaseSuccess.txt'
        );
        $this->getHttpRequest()->query->replace(['result' => 'abc123']);
        $response = $this->get($staticUrl);
        // should return 404, because controller won't be able to find a payment
        $this->assertEquals(404, $response->getStatusCode());

        // Add extension that will find the paymeng from the request params
        PaymentGatewayController::add_extension(PaymentGatewayControllerTestExtension::class);
        $this->setMockHttpResponse(
            'paymentexpress/tests/Mock/PxPayCompletePurchaseSuccess.txt'
        );
        $this->getHttpRequest()->query->replace(['result' => 'abc123']);
        $response = $this->get($staticUrl);
        // We should get a redirect to the complete url (shop/complete)
        $this->assertEquals(302, $response->getStatusCode());
        $headers = $response->getHeaders();
        $this->assertStringEndsWith(
            'shop/complete',
            $headers['location'],
            "redirected to shop/complete"
        );
        PaymentGatewayController::remove_extension('PaymentGatewayControllerTest_TestExtension');
    }

    public function testStaticRouteWithoutAction(): void
    {
        PaymentGatewayController::add_extension(PaymentGatewayControllerTestExtension::class);
        // Configure gateway to use static route
        Config::modify()->merge(GatewayInfo::class, 'PaymentExpress_PxPay', ['use_static_route' => true]);
        $staticUrl = 'paymentendpoint/gateway/PaymentExpress_PxPay?id=62b26e0a8a77f60cce3e9a7994087b0e';

        $response = $this->get($staticUrl);
        // should return 404, because there's no action
        $this->assertEquals(404, $response->getStatusCode());

        $response = $this->get($staticUrl . '&action=bogus');
        // should return 404, because the action is invalid
        $this->assertEquals(404, $response->getStatusCode());

        $response = $this->get($staticUrl . '&action=cancel');
        // We should get a redirect to the cancel url (shop/incomplete)
        $this->assertEquals(302, $response->getStatusCode());
        $headers = $response->getHeaders();
        $this->assertStringEndsWith(
            'shop/incomplete',
            $headers['location'],
            "redirected to shop/incomplete"
        );
        PaymentGatewayController::remove_extension(PaymentGatewayControllerTestExtension::class);
    }
}
