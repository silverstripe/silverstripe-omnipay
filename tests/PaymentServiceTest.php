<?php

declare(strict_types=1);

namespace SilverStripe\Omnipay\Tests;

use Omnipay\Common\GatewayFactory;
use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\AbstractGateway;
use SilverStripe\Omnipay\Exception\InvalidConfigurationException;
use Omnipay\Common\Message\NotificationInterface;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Service\PaymentService;
use SilverStripe\Omnipay\Service\ServiceFactory;
use SilverStripe\Omnipay\Tests\Extensions\PaymentTestPaymentExtensionHooks;
use SilverStripe\Omnipay\Tests\Extensions\TestNotifyResponseExtension;
use SilverStripe\Omnipay\Tests\Model\TestOffsiteGateway;
use SilverStripe\Omnipay\Tests\Service\TestGatewayFactory;

class PaymentServiceTest extends FunctionalTest
{
    use PaymentTestTrait;

    protected static $fixture_file = 'PaymentTest.yml';

    protected $autoFollowRedirection = false;

    protected PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        PaymentTestPaymentExtensionHooks::ResetAll();

        $this->factory = ServiceFactory::create();

        Payment::config()->set('allowed_gateways', [
            'PayPal_Express',
            'PaymentExpress_PxPay',
            'Manual',
            'Dummy'
        ]);

        // clear settings for PaymentExpress_PxPay (don't let user configs bleed into tests)
        Config::modify()
            ->remove(GatewayInfo::class, 'PaymentExpress_PxPay')
            ->set(GatewayInfo::class, 'PaymentExpress_PxPay', [
                'parameters' => [
                    'username' => 'EXAMPLEUSER',
                    'password' => '235llgwxle4tol23l'
                ]
            ]);

        //set up a payment here to make tests shorter
        $this->payment = Payment::create()
            ->setGateway("Dummy")
            ->setAmount(1222)
            ->setCurrency("GBP");

        Config::modify()->set(Injector::class, GatewayFactory::class, [
            'class' => TestGatewayFactory::class
        ]);

        TestGatewayFactory::$httpClient = $this->getHttpClient();
        TestGatewayFactory::$httpRequest = $this->getHttpRequest();

        $this->service = $this->factory->getService($this->payment, ServiceFactory::INTENT_PURCHASE);
    }

    public function testCancel(): void
    {
        $serviceResponse = $this->service->cancel();

        $this->assertEquals('Void', $this->payment->Status);
        $this->assertTrue($serviceResponse->isCancelled());
    }

    public function testGateway(): void
    {
        Config::modify()->merge(GatewayInfo::class, 'PaymentExpress_PxPay', [
            // set some invalid params
            'parameters' => [
                'DummyParameter' => 'DummyValue'
            ]
        ]);

        $gateway = $this->service->oGateway();
        $this->assertEquals('Dummy', $gateway->getShortName());

        // change the payment gateway
        $this->payment->Gateway = 'PaymentExpress_PxPay';

        $gateway = $this->service->oGateway();
        $this->assertEquals('PaymentExpress_PxPay', $gateway->getShortName());

        $expectedParams = [
            'username' => 'EXAMPLEUSER',
            'password' => '235llgwxle4tol23l'
        ];

        $this->assertEquals(
            // the gateway might return more parameters, but it should at least contain the expected params
            array_intersect_assoc($gateway->getParameters(), $expectedParams),
            $expectedParams
        );

        // The dummy parameter should not be in there
        $this->assertNotContains('DummyParameter', array_keys($gateway->getParameters()));
    }

    // Test a successful notification
    public function testHandleNotificationSuccess(): void
    {
        $service = $this->buildNotificationService(NotificationInterface::STATUS_COMPLETED);

        $serviceResponse = $service->handleNotification();

        // notification should be handled fine
        $this->assertFalse($serviceResponse->isError());
        // response should be flagged as notification
        $this->assertTrue($serviceResponse->isNotification());
        // response should have an instance of the notification attached
        $this->assertNotNull($serviceResponse->getOmnipayResponse());
        $this->assertInstanceOf(
            NotificationInterface::class,
            $serviceResponse->getOmnipayResponse()
        );
        $httpResponse = $serviceResponse->redirectOrRespond();
        $this->assertInstanceOf(HTTPResponse::class, $httpResponse);
        $this->assertEquals(200, $httpResponse->getStatusCode());
        $this->assertEquals('OK', $httpResponse->getBody());
    }

    // Test notification response modified by extension
    public function testHandleModifiedNotification(): void
    {
        PaymentService::add_extension(TestNotifyResponseExtension::class);

        $service = $this->buildNotificationService(NotificationInterface::STATUS_COMPLETED);

        $this->payment->setGateway('FantasyGateway');

        $serviceResponse = $service->handleNotification();

        // notification should be handled fine
        $this->assertFalse($serviceResponse->isError());
        // response should be flagged as notification
        $this->assertTrue($serviceResponse->isNotification());
        // response should have an instance of the notification attached
        $this->assertNotNull($serviceResponse->getOmnipayResponse());
        $this->assertInstanceOf(
            NotificationInterface::class,
            $serviceResponse->getOmnipayResponse()
        );

        $httpResponse = $serviceResponse->redirectOrRespond();
        $this->assertInstanceOf(HTTPResponse::class, $httpResponse);
        $this->assertEquals(200, $httpResponse->getStatusCode());
        $this->assertEquals('OK', $httpResponse->getBody());
        $this->assertEquals('apikey12345', $httpResponse->getHeader('X-FantasyGateway-Api'));

        // change to default gateway
        $service = $this->buildNotificationService(NotificationInterface::STATUS_COMPLETED);

        $serviceResponse = $service->handleNotification();

        // notification should be handled fine
        $this->assertFalse($serviceResponse->isError());
        // response should be flagged as notification
        $this->assertTrue($serviceResponse->isNotification());
        // response should have an instance of the notification attached
        $this->assertNotNull($serviceResponse->getOmnipayResponse());
        $this->assertInstanceOf(
            NotificationInterface::class,
            $serviceResponse->getOmnipayResponse()
        );

        $httpResponse = $serviceResponse->redirectOrRespond();
        $this->assertInstanceOf(HTTPResponse::class, $httpResponse);
        $this->assertEquals(200, $httpResponse->getStatusCode());
        // body will be SUCCESS instead of OK
        $this->assertEquals('SUCCESS', $httpResponse->getBody());

        PaymentService::remove_extension(TestNotifyResponseExtension::class);
    }

    // Test an error notification
    public function testHandleNotificationError(): void
    {
        $service = $this->buildNotificationService(NotificationInterface::STATUS_FAILED);

        $serviceResponse = $service->handleNotification();

        // notification should error
        $this->assertTrue($serviceResponse->isError());
        // response should be flagged as notification
        $this->assertTrue($serviceResponse->isNotification());
        // response should have an instance of the notification attached
        $this->assertNotNull($serviceResponse->getOmnipayResponse());
        $this->assertInstanceOf(
            NotificationInterface::class,
            $serviceResponse->getOmnipayResponse()
        );
    }

    // Test a pending notification
    public function testHandleNotificationPending(): void
    {
        $service = $this->buildNotificationService(NotificationInterface::STATUS_PENDING);

        $serviceResponse = $service->handleNotification();

        // notification should not error
        $this->assertFalse($serviceResponse->isError());
        // response should be flagged as notification
        $this->assertTrue($serviceResponse->isNotification());
        // response should be flagged as pending
        $this->assertTrue($serviceResponse->isAwaitingNotification());
        // response should have an instance of the notification attached
        $this->assertNotNull($serviceResponse->getOmnipayResponse());
        $this->assertInstanceOf(
            NotificationInterface::class,
            $serviceResponse->getOmnipayResponse()
        );
    }

    // Test a gateway that doesn't return an instance of NotificationInterface
    public function testHandleNotificationInvalid(): void
    {
        // build a notification that returns an AbstractResponse instead of the expected NotificationInterface
        $service = $this->buildNotificationService(
            NotificationInterface::STATUS_PENDING,
            AbstractResponse::class
        );

        $serviceResponse = $service->handleNotification();

        // notification should error
        $this->assertTrue($serviceResponse->isError());
        // response should be flagged as notification
        $this->assertTrue($serviceResponse->isNotification());
        // response should NOT have an instance of the response attached (since it's invalid)
        $this->assertNull($serviceResponse->getOmnipayResponse());
    }

    /**
     * Test with a gateway that doesn't implement `acceptNotification`.
     */
    public function testHandleNotificationWithIncompatibleGateway(): void
    {
        $payment = $this->payment->setGateway('PaymentExpress_PxPay');
        $paymentService = $this->factory->getService($payment, ServiceFactory::INTENT_PURCHASE);

        // build a gateway that doesn't have the `acceptNotification` method
        $stubGateway = $this->getMockBuilder(AbstractGateway::class)
            ->onlyMethods(['getName'])
            ->getMock();

        $paymentService->setGatewayFactory($this->stubGatewayFactory($stubGateway));

        // this should throw an exception
        $this->expectException(InvalidConfigurationException::class);
        $paymentService->handleNotification();
    }

    /**
     * @param class-string $contract Mock class for the object returned by acceptNotification()
     */
    protected function buildNotificationService(
        mixed $returnState,
        string $contract = NotificationInterface::class
    ) {
        $payment = $this->payment->setGateway('PaymentExpress_PxPay');
        $paymentService = $this->factory->getService($payment, ServiceFactory::INTENT_PURCHASE);

        //--------------------------------------------------------------------------------------------------------------
        // Notification response

        if ($contract === NotificationInterface::class) {
            $notificationResponse = $this->getMockBuilder(NotificationInterface::class)
                ->onlyMethods(['getTransactionStatus', 'getTransactionReference', 'getMessage', 'getData'])
                ->getMock();
            $notificationResponse
                ->method('getTransactionStatus')->willReturn($returnState);
        } else {
            $notificationResponse = $this->createMock($contract);
        }

        //--------------------------------------------------------------------------------------------------------------
        // Build the gateway

        $stubGateway = $this->getMockBuilder(TestOffsiteGateway::class)
            ->onlyMethods(['getName', 'acceptNotification'])
            ->getMock();

        $stubGateway->expects($this->once())
            ->method('acceptNotification')
            ->willReturn($notificationResponse);

        $paymentService->setGatewayFactory($this->stubGatewayFactory($stubGateway));

        return $paymentService;
    }
}
