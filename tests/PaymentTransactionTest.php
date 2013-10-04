<?php

class PaymentTransactionTest extends SapphireTest{

	static $fixture_file = array(
		'payment.yml'
	);

	function testCreateIdentifier(){
		$transaction = new PaymentTransaction();
		$identifier = $transaction->generateIdentifier();
		$this->assertNotNull($identifier);
		$this->assertNotEquals('',$identifier);
	}

	function testChangeIdentifier(){
		$transaction = $this->objFromFixture('PaymentTransaction','transaction1');
		$transaction->generateIdentifier();
		$transaction->Identifier = "somethingelse";
		$this->assertEquals("UNIQUEHASH23q5123tqasdf", $transaction->Identifier);
	}

}