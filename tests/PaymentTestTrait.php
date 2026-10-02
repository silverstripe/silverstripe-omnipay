<?php

declare(strict_types=1);

namespace SilverStripe\Omnipay\Tests;

use SilverStripe\Omnipay\Service\AuthorizeService;
use SilverStripe\Omnipay\Service\CreateCardService;
use SilverStripe\Omnipay\Service\PurchaseService;
use SilverStripe\Omnipay\Service\RefundService;
use SilverStripe\Omnipay\Service\CaptureService;
use SilverStripe\Omnipay\Service\VoidService;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Message;
use Omnipay\Common\GatewayFactory;
use Omnipay\Common\GatewayInterface;
use PHPUnit\Framework\MockObject\MockObject;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Service\ServiceFactory;
use SilverStripe\Omnipay\Tests\Extensions\PaymentTestPaymentExtensionHooks;
use SilverStripe\Omnipay\Tests\Service\TestGatewayFactory;
use Symfony\Component\HttpFoundation\Request;

trait PaymentTestTrait
{
    protected static $fixture_file = 'PaymentTest.yml';

    protected $autoFollowRedirection = false;

    protected Payment $payment;

    protected ServiceFactory $factory;

    protected $httpClient;

    protected $httpRequest;

    protected ?MockHandler $mockHandler = null;

    protected static array $factoryExtensions = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // remove all extensions applied to ServiceFactory
        static::$factoryExtensions = ServiceFactory::create()->getExtensionInstances();

        if (static::$factoryExtensions) {
            foreach (static::$factoryExtensions as $factoryExtension) {
                ServiceFactory::remove_extension(get_class($factoryExtension));
            }
        }

        // clear existing config for the factory (clear user defined settings)
        Config::modify()->remove('ServiceFactory', 'services');

        // Create the default service map
        Config::modify()->set(ServiceFactory::class, 'services', [
            'authorize' => AuthorizeService::class,
            'createcard' => CreateCardService::class,
            'purchase' => PurchaseService::class,
            'refund' => RefundService::class,
            'capture' => CaptureService::class,
            'void' => VoidService::class
        ]);

        Payment::add_extension(PaymentTestPaymentExtensionHooks::class);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        // Add removed extensions back once the tests have completed
        if (static::$factoryExtensions) {
            foreach (static::$factoryExtensions as $factoryExtension) {
                ServiceFactory::add_extension(get_class($factoryExtension));
            }
        }

        Payment::remove_extension(PaymentTestPaymentExtensionHooks::class);
    }

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
    }

    protected function getHttpClient()
    {
        if (null === $this->httpClient) {
            if ($this->mockHandler === null) {
                $this->mockHandler = new MockHandler();
            }

            $client = new Client([
                'handler' => $this->mockHandler,
            ]);

            $this->httpClient = new \Omnipay\Common\Http\Client(new \Http\Adapter\Guzzle7\Client($client));
        }

        return $this->httpClient;
    }

    public function getHttpRequest()
    {
        if (null === $this->httpRequest) {
            $this->httpRequest = new Request;
        }

        return $this->httpRequest;
    }

    protected function setMockHttpResponse($paths)
    {
        if ($this->mockHandler === null) {
            throw new Exception('HTTP client not initialised before adding mock response.');
        }

        $testspath = BASE_PATH . '/vendor/omnipay'; //TODO: improve?

        foreach ((array)$paths as $path) {
            $this->mockHandler->append(
                Message::parseResponse(file_get_contents("{$testspath}/{$path}"))
            );
        }

        return $this->mockHandler;
    }

    /**
     * @param GatewayInterface|MockObject $stubGateway
     * @return MockObject&GatewayFactory
     */
    protected function stubGatewayFactory($stubGateway)
    {
        $factory = $this->createMock(GatewayFactory::class);
        $factory->method('create')->willReturn($stubGateway);
        return $factory;
    }
}
