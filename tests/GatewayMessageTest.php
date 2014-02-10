<?php

class GatewayTransactionTest extends SapphireTest{

	public static $fixture_file = array(
		'payment.yml'
	);

	public function testCreateIdentifier() {
		$transaction = new GatewayMessage();
		$identifier = $transaction->generateIdentifier();
		$this->assertNotNull($identifier);
		$this->assertNotEquals('', $identifier);
	}

	public function testChangeIdentifier() {
		$transaction = $this->objFromFixture('GatewayMessage', 'message1');
		$transaction->generateIdentifier();
		$transaction->Identifier = "somethingelse";
		$this->assertEquals("UNIQUEHASH23q5123tqasdf", $transaction->Identifier);
	}

}
