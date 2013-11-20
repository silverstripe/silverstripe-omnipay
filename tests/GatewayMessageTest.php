<?php

class GatewayTransactionTest extends SapphireTest{

	static $fixture_file = array(
		'payment.yml'
	);

	function testCreateIdentifier(){
		$transaction = new GatewayMessage();
		$identifier = $transaction->generateIdentifier();
		$this->assertNotNull($identifier);
		$this->assertNotEquals('',$identifier);
	}

	function testChangeIdentifier(){
		$transaction = $this->objFromFixture('GatewayMessage','transaction1');
		$transaction->generateIdentifier();
		$transaction->Identifier = "somethingelse";
		$this->assertEquals("UNIQUEHASH23q5123tqasdf", $transaction->Identifier);
	}

}