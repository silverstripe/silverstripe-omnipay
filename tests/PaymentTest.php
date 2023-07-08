<?php

namespace SilverStripe\Omnipay\Tests;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\Tests\Extensions\PaymentTestPaymentExtensionHooks;
use SilverStripe\Omnipay\Service\PaymentService;
use SilverStripe\Omnipay\Service\ServiceFactory;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Omnipay\Tests\Service\TestGatewayFactory;

abstract class PaymentTest extends FunctionalTest
{
    protected static $fixture_file = 'PaymentTest.yml';

    protected $autoFollowRedirection = false;

    /** @var Payment */
    protected $payment;

    /** @var \SilverStripe\Omnipay\Service\ServiceFactory */
    protected $factory;

    protected $httpClient;

    protected $httpRequest;

    /** @var \GuzzleHttp\Handler\MockHandler */
    protected $mockHandler = null;

    protected static $factoryExtensions;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // remove all extensions applied to ServiceFactory
        static::$factoryExtensions = ServiceFactory::create()->getExtensionInstances();

        if (static::$factoryExtensions) {
            foreach (static::$factoryExtensions as $extension) {
                ServiceFactory::remove_extension($extension);
            }
        }

        // clear existing config for the factory (clear user defined settings)
        Config::modify()->remove('ServiceFactory', 'services');

        // Create the default service map
        Config::modify()->set(ServiceFactory::class, 'services', [
            'authorize' => '\SilverStripe\Omnipay\Service\AuthorizeService',
            'createcard' => '\SilverStripe\Omnipay\Service\CreateCardService',
            'purchase' => '\SilverStripe\Omnipay\Service\PurchaseService',
            'refund' => '\SilverStripe\Omnipay\Service\RefundService',
            'capture' => '\SilverStripe\Omnipay\Service\CaptureService',
            'void' => '\SilverStripe\Omnipay\Service\VoidService'
        ]);

        Payment::add_extension(PaymentTestPaymentExtensionHooks::class);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        // Add removed extensions back once the tests have completed
        if (static::$factoryExtensions) {
            foreach (static::$factoryExtensions as $extension) {
                ServiceFactory::add_extension($extension);
            }
        }

        Payment::remove_extension(PaymentTestPaymentExtensionHooks::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        PaymentTestPaymentExtensionHooks::ResetAll();

        $this->factory = ServiceFactory::create();

        Payment::config()->allowed_gateways = [
            'PayPal_Express',
            'PaymentExpress_PxPay',
            'Manual',
            'Dummy'
        ];

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

        Config::modify()->set(Injector::class, 'Omnipay\Common\GatewayFactory', [
            'class' => TestGatewayFactory::class
        ]);

        TestGatewayFactory::$httpClient = $this->getHttpClient();
        TestGatewayFactory::$httpRequest = $this->getHttpRequest();
    }

    protected function getHttpClient()
    {
        if (null === $this->httpClient) {
            if ($this->mockHandler === null) {
                $this->mockHandler = new \GuzzleHttp\Handler\MockHandler();
            }

            $guzzle = new \GuzzleHttp\Client([
                'handler' => $this->mockHandler,
            ]);

            $this->httpClient = new \Omnipay\Common\Http\Client(new \Http\Adapter\Guzzle7\Client($guzzle));
        }

        return $this->httpClient;
    }

    public function getHttpRequest()
    {
        if (null === $this->httpRequest) {
            $this->httpRequest = new \Symfony\Component\HttpFoundation\Request;
        }

        return $this->httpRequest;
    }

    protected function setMockHttpResponse($paths)
    {
        if ($this->mockHandler === null) {
            throw new \Exception('HTTP client not initialised before adding mock response.');
        }

        $testspath = BASE_PATH . '/vendor/omnipay'; //TODO: improve?

        foreach ((array)$paths as $path) {
            $this->mockHandler->append(
                \GuzzleHttp\Psr7\Message::parseResponse(file_get_contents("{$testspath}/{$path}"))
            );
        }

        return $this->mockHandler;
    }

    /**
     * @param GatewayInterface|PHPUnit_Framework_MockObject_MockObject $stubGateway
     * @return PHPUnit_Framework_MockObject_MockObject|GatewayFactory
     */
    protected function stubGatewayFactory($stubGateway)
    {
        $factory = $this->getMockBuilder('Omnipay\Common\GatewayFactory')->getMock();
        $factory->expects($this->any())->method('create')->will($this->returnValue($stubGateway));
        return $factory;
    }
}
