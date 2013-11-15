<?php

class GatewayTransactionTest extends SapphireTest{

	static $fixture_file = array(
		'payment.yml'
	);

	function testCreateIdentifier(){
		$transaction = new GatewayTransaction();
		$identifier = $transaction->generateIdentifier();
		$this->assertNotNull($identifier);
		$this->assertNotEquals('',$identifier);
	}

	function testChangeIdentifier(){
		$transaction = $this->objFromFixture('GatewayTransaction','transaction1');
		$transaction->generateIdentifier();
		$transaction->Identifier = "somethingelse";
		$this->assertEquals("UNIQUEHASH23q5123tqasdf", $transaction->Identifier);
	}

	
	
	function testPurchaseTransaction(){

		//successful: assert that transaction exists
		
		//failed

	}
	
	function testCompletePurchaseTransaction(){

	}

	function testVoidTransaction(){

	}

	//TODO: test transactions are created whenever gateway transiations

	//authorise
	//capture
	//void
	//refund

}