<?php

class PaymentTest extends SapphireTest{
	
	static $fixture_file = array(
		'payment.yml'
	);

	protected $payment;
	protected $httpClient, $httpRequest;

	function setUp() {
		parent::setUp();
		$cfg = Config::inst();
		$cfg->remove("Payment", "allowed_gateways");
		$cfg->update("Payment", "allowed_gateways", array(
			'PayPal_Express',
			'PaymentExpress_PxPay',
			'Manual',
			'Dummy'
		));
		//set up a payment here to make tests shorter
		$this->payment = Payment::create()
		 			->setGateway("Dummy")
		 			->setAmount(1222)
		 			->setCurrency("GBP")
		 			->setHTTPClient($this->getHttpClient())
		 			->setHTTPRequest($this->getHttpRequest());
	}

	protected function getHttpClient() {
		if (null === $this->httpClient) {
			$this->httpClient = new Guzzle\Http\Client;
		}

		return $this->httpClient;
	}

	public function getHttpRequest() {
        if (null === $this->httpRequest) {
            $this->httpRequest = new Symfony\Component\HttpFoundation\Request;
        }

        return $this->httpRequest;
    }

    protected function setMockHttpResponse($paths) {
    	$testspath = BASE_PATH.'/vendor/omnipay/omnipay/tests/Omnipay'; //TODO: improve?
       // $this->mockHttpRequests = array();
        //$that = $this;
        $mock = new Guzzle\Plugin\Mock\MockPlugin(null, true);
        $this->getHttpClient()->getEventDispatcher()->removeSubscriber($mock);
        // $mock->getEventDispatcher()->addListener('mock.request', function(Event $event) use ($that) {
        //     $that->addMockedHttpRequest($event['request']);
        // });
        foreach ((array) $paths as $path) {
            $mock->addResponse($testspath.'/'.$path);
        }
        $this->getHttpClient()->getEventDispatcher()->addSubscriber($mock);

        return $mock;
    }

}