<?php

class PaymentModelTest extends PaymentTest {

	public function testParameterSetup() {
		$payment = Payment::create()
					->init("Manual", 23.56, "NZD");

		$this->assertEquals("Created", $payment->Status);
		$this->assertEquals(23.56, $payment->Amount);
		$this->assertEquals("NZD", $payment->Currency);
		$this->assertEquals("Manual", $payment->Gateway);
	}

	public function testCMSFields() {
		$fields = Payment::create()->getCMSFields();
	}

	public function testTitle() {
		$payment = $this->objFromFixture("Payment", "payment1");
		$this->assertEquals(
			"Manual NZ$20.23 10/10/2013",
			$payment->Title
		);
	}

	public function testSupportedGateways() {
		$expected = array(
			'PayPal_Express' => 'PayPal Express',
			'PaymentExpress_PxPay' => 'PaymentExpress PxPay',
			'Manual' => 'Manual',
			'Dummy' => 'Dummy'
		);
		$gateways = GatewayInfo::get_supported_gateways();
		$this->assertArrayHasKey('PayPal_Express', $gateways);
		$this->assertArrayHasKey('PaymentExpress_PxPay', $gateways);
		$this->assertArrayHasKey('Manual', $gateways);
		$this->assertArrayHasKey('Dummy', $gateways);
	}

	public function testCreateIdentifier() {
		$payment = new Payment();
		$payment->write();
		$this->assertNotNull($payment->Identifier);
		$this->assertNotEquals('', $payment->Identifier);
		$this->assertEquals(30, strlen($payment->Identifier));
	}

	public function testChangeIdentifier() {
		$payment = $this->objFromFixture('Payment', 'payment2');
		$payment->Identifier = "somethingelse";
		$this->assertEquals("UNIQUEHASH23q5123tqasdf", $payment->Identifier);
	}

}
