<?php

class PaymentTest extends SapphireTest {
	
	static $fixture_file = array(
		'payment.yml'
	);

	protected $payment;
	
	function setUp() {
		parent::setUp();
		Config::inst()->update("Payment", "allowed_gateways", array(
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

	function testSupportedGateways() {
		$gateways = Payment::get_supported_gateways();
		$this->assertEquals(array(
			'PayPal_Express' => 'PayPal Express',
			'PaymentExpress_PxPay' => 'PaymentExpress PxPay',
			'Manual' => 'Manual',
			'Dummy' => 'Dummy'
		), $gateways, "supported gateways array is created correctly");
	}

	function testParameterSetup(){
		$payment = Payment::create()
					->init("Manual",23.56,"NZD")
					->setReturnUrl("abc/123")
					->setCancelUrl("xyz/blah/2345235?andstuff=124124#hash");

		$this->assertEquals("Created", $payment->Status);
		$this->assertEquals(23.56, $payment->Amount);
		$this->assertEquals("NZD", $payment->Currency);
		$this->assertEquals("Manual", $payment->Gateway);
		$this->assertEquals("abc/123",$payment->getReturnUrl());
		$this->assertEquals("xyz/blah/2345235?andstuff=124124#hash",$payment->getCancelUrl());
	}
	
	function testDummyOnSitePurchase() {
		$payment = $this->payment;
		$response = $payment->purchase(array(
			'number' => '4242424242424242', //this creditcard will succeed
			'expiryMonth' => '5',
			'expiryYear' => date("Y",strtotime("+1 year"))
		));
		$this->assertEquals("Captured", $payment->Status, "is the status updated");
		$this->assertEquals(1222, $payment->Amount);
		$this->assertEquals("GBP", $payment->Currency);
		$this->assertEquals("Dummy", $payment->Gateway);
		$this->assertTrue($response->isSuccessful());
		$this->assertFalse($response->isRedirect());

		//values cannot be changed after successful purchase
		$payment->Amount = 2;
		$payment->Currency = "NZD";
		$payment->Gateway = "X";
		$payment->write();

		$this->assertEquals(1222, $payment->Amount);
		$this->assertEquals("GBP", $payment->Currency);
		$this->assertEquals("Dummy", $payment->Gateway);
	}

	function testFailedDummyOnSitePurchase() {
		$payment = $this->payment;
		$response = $payment->purchase(array(
			'number' => '4111111111111111',  //this creditcard will decline
			'expiryMonth' => '5',
			'expiryYear' => date("Y",strtotime("+1 year"))
		));
		$this->assertEquals("Created", $payment->Status, "is the status has not been updated");
		$this->assertEquals(1222, $payment->Amount);
		$this->assertEquals("GBP", $payment->Currency);
		$this->assertFalse($response->isSuccessful());
		$this->assertFalse($response->isRedirect());
	}

	function testOnSitePurchase() {
		$payment = $this->payment->setGateway('PaymentExpress_PxPost');
		$this->setMockHttpResponse('PaymentExpress/Mock/PxPostPurchaseSuccess.txt');//add success mock response from file
	 	$response = $payment->purchase(array(
			'number' => '4242424242424242', //this creditcard will succeed
			'expiryMonth' => '5',
			'expiryYear' => date("Y",strtotime("+1 year"))
		));
		$this->assertTrue($response->isSuccessful()); //payment has not been captured
		$this->assertFalse($response->isRedirect());
		$this->assertSame("Captured",$payment->Status);
	}

	function testInvalidOnsitePurchase() {
		//provide invalid/incorrect data
	}

	function testFailedOnSitePurchase() {
		$payment = $this->payment->setGateway('PaymentExpress_PxPost');
		$this->setMockHttpResponse('PaymentExpress/Mock/PxPostPurchaseFailure.txt');//add success mock response from file
	 	$response = $payment->purchase(array(
			'number' => '4111111111111111', //this creditcard will decline
			'expiryMonth' => '5',
			'expiryYear' => date("Y",strtotime("+1 year"))
		));
		$this->assertFalse($response->isSuccessful()); //payment has not been captured
		$this->assertFalse($response->isRedirect());
		$this->assertSame("Created",$payment->Status);
	}

	function testOffSitePurchase(){
		$payment = $this->payment->setGateway('PaymentExpress_PxPay');
		$this->setMockHttpResponse('PaymentExpress/Mock/PxPayPurchaseSuccess.txt');//add success mock response from file
	 	$response = $payment->purchase();
		$this->assertFalse($response->isSuccessful()); //payment has not been captured
		$this->assertTrue($response->isRedirect());
		$this->assertSame('https://sec.paymentexpress.com/pxpay/pxpay.aspx?userid=Developer&request=v5H7JrBTzH-4Whs__1iQnz4RGSb9qxRKNR4kIuDP8kIkQzIDiIob9GTIjw_9q_AdRiR47ViWGVx40uRMu52yz2mijT39YtGeO7cZWrL5rfnx0Mc4DltIHRnIUxy1EO1srkNpxaU8fT8_1xMMRmLa-8Fd9bT8Oq0BaWMxMquYa1hDNwvoGs1SJQOAJvyyKACvvwsbMCC2qJVyN0rlvwUoMtx6gGhvmk7ucEsPc_Cyr5kNl3qURnrLKxINnS0trdpU4kXPKOlmT6VacjzT1zuj_DnrsWAPFSFq-hGsow6GpKKciQ0V0aFbAqECN8rl_c-aZWFFy0gkfjnUM4qp6foS0KMopJlPzGAgMjV6qZ0WfleOT64c3E-FRLMP5V_-mILs8a', $response->oResponse()->getRedirectUrl());
		$this->assertSame("Authorized",$payment->Status);

		//... user would normally be redirected to external gateway at this point ...

		$this->setMockHttpResponse('PaymentExpress/Mock/PxPayCompletePurchaseSuccess.txt'); //mock complete purchase response
		$this->getHttpRequest()->query->replace(array('result' => 'abc123')); //mock the 'result' get variable into the current request
		$response = $payment->completePurchase(); //something going wrong here
		$this->assertTrue($response->isSuccessful());
		$this->assertSame("Captured", $payment->Status);
	}

	function testFailedOffSitePurchase(){
		$payment = $this->payment->setGateway('PaymentExpress_PxPay');
		$this->setMockHttpResponse('PaymentExpress/Mock/PxPayPurchaseFailure.txt');//add success mock response from file
		$response = $payment->purchase();
		$this->assertFalse($response->isSuccessful()); //payment has not been captured
		$this->assertFalse($response->isRedirect()); //redirect won't occur, because of failure
		$this->assertSame("Created",$payment->Status);
	}

	function testRedirectUrl() {
		$payment = Payment::create()
					->setReturnUrl("abc/123")
					->setCancelUrl("xyz/blah/2345235?andstuff=124124#hash");
		$this->assertEquals("abc/123",$payment->getReturnUrl());
		$this->assertEquals("xyz/blah/2345235?andstuff=124124#hash",$payment->getCancelUrl());
	}
	
	function testNonExistantGateway() {
		//exception when trying to run functions that require a gateway
		$this->setExpectedException("RuntimeException");
		$result = Payment::create()
			->init("PxPayGateway", 100, "NZD")
			->setReturnUrl("complete")
			->purchase();

		//but we can still use the payment model in calculations etc
		$totalNZD = Payment::get()->filter('MoneyCurrency',"NZD")->sum();
		$this->assertEquals(27.23,$totalNZD);
	}


	//TODO: testOnSiteAuthorizeCapture
	//TODO: testOffSiteAuthorizeCapture
	//TODO: testVoid
	//TODO: testRefund


	//mock testing functionality - blatently ripped from omnipay testing

	protected $httpClient, $httpRequest;

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
