<?php
use SilverStripe\Omnipay\Service\PaymentService;
use SilverStripe\Omnipay\Service\ServiceFactory;


abstract class PaymentTest extends FunctionalTest
{
    public static $fixture_file = array(
        'payment.yml'
    );

    //don't follow redirect urls
    protected $autoFollowRedirection = false;

    /** @var Payment */
    protected $payment;

    /** @var \SilverStripe\Omnipay\Service\ServiceFactory */
    protected $factory;

    protected $httpClient, $httpRequest;

    private $factoryExtensions;

    public function setUpOnce()
    {
        parent::setUpOnce();

        // remove all extensions applied to ServiceFactory
        $this->factoryExtensions = Object::get_extensions('ServiceFactory');

        if($this->factoryExtensions){
            foreach ($this->factoryExtensions as $extension){
                ServiceFactory::remove_extension($extension);
            }
        }

        // clear existing config for the factory (clear user defined settings)
        Config::inst()->remove('ServiceFactory', 'services');

        // Create the default service map
        Config::inst()->update('ServiceFactory', 'services', array(
            'authorize' => '\SilverStripe\Omnipay\Service\AuthorizeService',
            'purchase' => '\SilverStripe\Omnipay\Service\PurchaseService',
            'refund' => '\SilverStripe\Omnipay\Service\RefundService',
            'capture' => '\SilverStripe\Omnipay\Service\CaptureService',
            'void' => '\SilverStripe\Omnipay\Service\VoidService'
        ));
    }

    public function tearDownOnce()
    {
        parent::tearDownOnce();

        // Add removed extensions back once the tests have completed
        if($this->factoryExtensions){
            foreach ($this->factoryExtensions as $extension){
                ServiceFactory::add_extension($extension);
            }
        }
    }

    public function setUp()
    {
        parent::setUp();

        $this->factory = ServiceFactory::create();

        Payment::config()->allowed_gateways = array(
            'PayPal_Express',
            'PaymentExpress_PxPay',
            'Manual',
            'Dummy'
        );

        // clear settings for PaymentExpress_PxPay (don't let user configs bleed into tests)
        Config::inst()->remove('GatewayInfo', 'PaymentExpress_PxPay');
        Config::inst()->update('GatewayInfo', 'PaymentExpress_PxPay', array(
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
            $this->httpClient = new Guzzle\Http\Client;
        }

        return $this->httpClient;
    }

    public function getHttpRequest()
    {
        if (null === $this->httpRequest) {
            $this->httpRequest = new Symfony\Component\HttpFoundation\Request;
        }

        return $this->httpRequest;
    }

    protected function setMockHttpResponse($paths)
    {
        $testspath = BASE_PATH . '/vendor/omnipay'; //TODO: improve?

        $mock = new Guzzle\Plugin\Mock\MockPlugin(null, true);

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
