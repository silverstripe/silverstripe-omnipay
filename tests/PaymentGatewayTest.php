<?php

class PaymentGatewayTest extends PaymentTest {

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
		$payment->Gateway = "XYZ";
		$payment->write();

		$this->assertEquals(1222, $payment->Amount);
		$this->assertEquals("GBP", $payment->Currency);
		$this->assertEquals("Dummy", $payment->Gateway);

		//check messaging
		$this->assertDOSContains(array(
			array('ClassName' => 'PurchaseRequest'),
			array('ClassName' => 'PurchasedResponse')
		), $payment->Messages());
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

		//check messaging
		$this->assertDOSContains(array(
			array('ClassName' => 'PurchaseRequest'),
			array('ClassName' => 'PurchaseError')
		), $payment->Messages());
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

		//check messaging
		$this->assertDOSContains(array(
			array('ClassName' => 'PurchaseRequest'),
			array('ClassName' => 'PurchasedResponse')
		), $payment->Messages());
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

		//check messaging
		$this->assertDOSContains(array(
			array('ClassName' => 'PurchaseRequest'),
			array('ClassName' => 'PurchaseError')
		), $payment->Messages());
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

		//check messaging
		$this->assertDOSContains(array(
			array('ClassName' => 'PurchaseRequest'),
			array('ClassName' => 'PurchaseRedirectResponse'),
			array('ClassName' => 'CompletePurchaseRequest'),
			array('ClassName' => 'PurchasedResponse')
		), $payment->Messages());
	}

	function testFailedOffSitePurchase(){
		$payment = $this->payment->setGateway('PaymentExpress_PxPay');
		$this->setMockHttpResponse('PaymentExpress/Mock/PxPayPurchaseFailure.txt');//add success mock response from file
		$response = $payment->purchase();
		$this->assertFalse($response->isSuccessful()); //payment has not been captured
		$this->assertFalse($response->isRedirect()); //redirect won't occur, because of failure
		$this->assertSame("Created",$payment->Status);

		//check messaging
		$this->assertDOSContains(array(
			array('ClassName' => 'PurchaseRequest'),
			array('ClassName' => 'PurchaseError'),
		), $payment->Messages());

		//TODO: fail in various ways
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
		
		$payment = $this->payment
			->init("PxPayGateway", 100, "NZD")
			->setReturnUrl("complete");
		$this->setExpectedException("RuntimeException");		
		$result = $payment->purchase();

		//but we can still use the payment model in calculations etc
		$totalNZD = Payment::get()->filter('MoneyCurrency',"NZD")->sum();
		$this->assertEquals(27.23, $totalNZD);

		//TODO: call gateway functions
		//$payment->purchase();
		//$payment->completePurchase();
		//$payment->refund();
		//$payment->void();
	}

	//TODO: testOnSiteAuthorizeCapture
	//TODO: testOffSiteAuthorizeCapture
	//TODO: testVoid
	//TODO: testRefund™

}