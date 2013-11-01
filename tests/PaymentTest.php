<?php

class PaymentTest extends SapphireTest {
	
	static $fixture_file = array(
		'payment.yml'
	);
	
	function setUp() {
		parent::setUp();
		Config::inst()->update("Payment", "allowed_gateways", array(
			'PayPal_Express',
			'PaymentExpress_PxPay',
			'Manual',
			'Dummy'
		));
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

	function testSetup(){
		$payment = Payment::create()->init("Manual",23.56,"NZD");
		$this->assertEquals("Created", $payment->Status);
		$this->assertEquals(23.56, $payment->Amount);
		$this->assertEquals("NZD", $payment->Currency);
		$this->assertEquals("Manual", $payment->Gateway);
	}
	
	function testSuccessfulPurchase() {
		$payment = Payment::create()
		 			->setGateway("Dummy")
		 			->setAmount(1222)
		 			->setCurrency("GBP");
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

	function testFailedPurchase() {
		$payment = Payment::create()
	 			->setGateway("Dummy")
	 			->setAmount(1222)
	 			->setCurrency("GBP");
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

	function testRedirectPurchase() {
		//url is correct
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

}
