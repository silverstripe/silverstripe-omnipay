<?php

class PaymentTest extends FunctionalTest{

	public static $fixture_file = array(
		'payment.yml'
	);

	//don't follow redirect urls
	protected $autoFollowRedirection = false;

	protected $payment;
	protected $httpClient;
	protected $httpRequest;

	public function setUp() {
		parent::setUp();
		Payment::config()->allowed_gateways = array(
			'PayPal_Express',
			'PaymentExpress_PxPay',
			'Manual',
			'Dummy'
		);
		Payment::config()->parameters = array(
			'PaymentExpress_PxPay' => array(
				'username' => 'EXAMPLEUSER',
				'password' => '235llgwxle4tol23l'
			)
		);

		//set up a payment here to make tests shorter
		$this->payment = Payment::create()
						->setGateway("Dummy")
						->setAmount(1222)
						->setCurrency("GBP");

		PaymentService::set_http_client($this->getHttpClient());
		PaymentService::set_http_request($this->getHttpRequest());
	}

	protected function getHttpClient() {
		if (null === $this->httpClient) {
			$this->httpClient = new Guzzle\Http\Client;
		}

		return $this->httpClient;
	}

	public function getHttpRequest() {
		if(null === $this->httpRequest) {
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
