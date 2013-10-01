<?php

class PaymentTest extends SapphireTest{
	
	static $fixture_file = array(
		'payment.yml'
	);
	
	function setUp(){
		parent::setUp();

	}

	function testSupportedGateways(){
		//does it produce the right array?
	}

	function testSetup(){
		$payment = Payment::create()
					->setGateway("Manual")
					->setAmount(23.56)
					->setCurrency("NZD");

		$this->assertEquals("Created", $payment->Status);
		$this->assertEquals(23.56, $payment->AmountAmount);
		$this->assertEquals("NZD", $payment->AmountCurrency);
		$this->assertEquals("Manual", $payment->Gateway);
	}
	
	function testSuccessfulPurchase() {

		$payment = Payment::create(array(
				'Amount' => 1222,
				'Currency' => 'GBP',
				'Gateway' => 'Dummy'
			));

		$payment->purchase(array(
			
		),array(

		));

		//is the status updated
		//check transactions
		
	}

	function testRedirectPurchase() {
		//url is correct
	}

	function testAuthorize(){
		// 
	}

	//Test payment controller
		//redirect from offsite server

}