<?php

namespace SilverStripe\Omnipay\Tests;

use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\Tests\Extensions\PaymentTestPaymentExtensionHooks;
use SilverStripe\Omnipay\Service\PaymentService;
use SilverStripe\Omnipay\Service\ServiceFactory;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Core\Config\Config;

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

    protected static $factoryExtensions;

    public static function setUpBeforeClass()
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
        Config::modify()->set(ServiceFactory::class, 'services', array(
            'authorize' => '\SilverStripe\Omnipay\Service\AuthorizeService',
            'createcard' => '\SilverStripe\Omnipay\Service\CreateCardService',
            'purchase' => '\SilverStripe\Omnipay\Service\PurchaseService',
            'refund' => '\SilverStripe\Omnipay\Service\RefundService',
            'capture' => '\SilverStripe\Omnipay\Service\CaptureService',
            'void' => '\SilverStripe\Omnipay\Service\VoidService'
        ));

        Payment::add_extension(PaymentTestPaymentExtensionHooks::class);
    }

    public static function tearDownAfterClass()
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

    protected function setUp()
    {
        parent::setUp();

        PaymentTestPaymentExtensionHooks::ResetAll();

        // don't log test payments to file
        Config::modify()->set(Payment::class, 'file_logging', 0);

        $this->factory = ServiceFactory::create();

        Payment::config()->allowed_gateways = array(
            'PayPal_Express',
            'PaymentExpress_PxPay',
            'Manual',
            'Dummy'
        );

        // clear settings for PaymentExpress_PxPay (don't let user configs bleed into tests)
        Config::inst()->remove(GatewayInfo::class, 'PaymentExpress_PxPay');
        Config::modify()->set(GatewayInfo::class, 'PaymentExpress_PxPay', array(
            'parameters' => array(
                'username' => 'EXAMPLEUSER',
                'password' => '235llgwxle4tol23l'
            )
        ));

        //set up a payment here to make tests shorter
        $this->payment = Payment::create()
            ->setGateway("Dummy")
            ->setAmount(1222)
            ->setCurrency("GBP");

        PaymentService::setHttpClient($this->getHttpClient());
        PaymentService::setHttpRequest($this->getHttpRequest());
    }

    protected function getHttpClient()
    {
        if (null === $this->httpClient) {
            $this->httpClient = new \Guzzle\Http\Client;
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
        $testspath = BASE_PATH . '/vendor/omnipay'; //TODO: improve?

        $mock = new \Guzzle\Plugin\Mock\MockPlugin(null, true);

        $this->getHttpClient()->getEventDispatcher()->removeSubscriber($mock);
        foreach ((array)$paths as $path) {
            $mock->addResponse($testspath . '/' . $path);
        }

        $this->getHttpClient()->getEventDispatcher()->addSubscriber($mock);

        return $mock;
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
