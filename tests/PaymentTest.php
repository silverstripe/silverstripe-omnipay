<?php

class PaymentTest extends SapphireTest{
	
	static $fixture_file = array(
		'payment.yml'
	);
	
	function setUp(){
		parent::setUp();

		Config::inst()->update("Payment", "allowed_gateways", array(
			'PayPal_Express',
			'PaymentExpress_PxPay',
			'Manual',
			'Dummy'
		));

	}

	function testSupportedGateways(){
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
		$response = $payment->purchase(array(),array(
			'number' => '4242424242424242',
			'expiryMonth' => '5',
			'expiryYear' => date("Y",strtotime("+1 year"))
		));
		$this->assertEquals("Captured", $payment->Status, "is the status updated");
		//TODO: check transactions
		$transactions = $payment->Transactions();
	}

	function testFailedPurchase() {
		//
	}

	function testRedirectPurchase() {
		//url is correct
	}

	function testAuthorize(){
		// 
	}

	//Test payment controller
		//redirect from offsite server

	//Test trying to use a payment type that isn't allowed
}