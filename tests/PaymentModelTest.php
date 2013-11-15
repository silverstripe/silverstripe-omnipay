<?php

class PaymentModelTest extends PaymentTest {
	
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

	function testSupportedGateways() {
		$gateways = Payment::get_supported_gateways();
		$this->assertEquals(array(
			'PayPal_Express' => 'PayPal Express',
			'PaymentExpress_PxPay' => 'PaymentExpress PxPay',
			'Manual' => 'Manual',
			'Dummy' => 'Dummy'
		), $gateways, "supported gateways array is created correctly");
	}

	//TODO: test 

}
