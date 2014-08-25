<?php

class PurchaseServiceTest extends PaymentTest {

	public function testDummyOnSitePurchase() {
		$payment = $this->payment;
		$service = new PurchaseService($payment);
		$response = $service->purchase(array(
			'firstName' => 'joe',
			'lastName' => 'bloggs',
			'number' => '4242424242424242', //this creditcard will succeed
			'expiryMonth' => '5',
			'expiryYear' => date("Y", strtotime("+1 year"))
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

	public function testFailedDummyOnSitePurchase() {
		$payment = $this->payment;
		$service = new PurchaseService($payment);
		$response = $service->purchase(array(
			'firstName' => 'joe',
			'lastName' => 'bloggs',
			'number' => '4111111111111111',  //this creditcard will decline
			'expiryMonth' => '5',
			'expiryYear' => date("Y", strtotime("+1 year"))
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

	public function testOnSitePurchase() {
		$payment = $this->payment->setGateway('PaymentExpress_PxPost');
		$service = new PurchaseService($payment);
		$this->setMockHttpResponse('PaymentExpress/Mock/PxPostPurchaseSuccess.txt');//add success mock response from file
		$response = $service->purchase(array(
			'firstName' => 'joe',
			'lastName' => 'bloggs',
			'number' => '4242424242424242', //this creditcard will succeed
			'expiryMonth' => '5',
			'expiryYear' => date("Y", strtotime("+1 year"))
		));
		$this->assertTrue($response->isSuccessful());
		$this->assertFalse($response->isRedirect());
		$this->assertSame("Captured", $payment->Status, "has the payment been captured");

		//check messaging
		$this->assertDOSContains(array(
			array('ClassName' => 'PurchaseRequest'),
			array('ClassName' => 'PurchasedResponse')
		), $payment->Messages());
	}

	public function testInvalidOnsitePurchase() {
		$payment = $this->payment->setGateway("PaymentExpress_PxPost");
		$service = new PurchaseService($payment);
		//pass no card details nothing
		$response = $service->purchase(array());

		//check messaging
		$this->assertFalse($response->isSuccessful()); //payment has not been captured
		$this->assertFalse($response->isRedirect());
		$this->assertDOSContains(array(
			array('ClassName' => 'PurchaseError')
		), $payment->Messages());

		//TODO:
			//invalid/incorrect card number/date..lhun check (InvalidCreditCardException)
			//InvalidRequestException thrown when gateway needs specific parameters
		$this->markTestIncomplete();
	}

	public function testFailedOnSitePurchase() {
		$payment = $this->payment->setGateway('PaymentExpress_PxPost');
		$service = new PurchaseService($payment);
		$this->setMockHttpResponse('PaymentExpress/Mock/PxPostPurchaseFailure.txt');//add success mock response from file
		$response = $service->purchase(array(
			'number' => '4111111111111111', //this creditcard will decline
			'expiryMonth' => '5',
			'expiryYear' => date("Y", strtotime("+1 year"))
		));
		$this->assertFalse($response->isSuccessful()); //payment has not been captured
		$this->assertFalse($response->isRedirect());
		$this->assertSame("Created", $payment->Status);

		//check messaging
		$this->assertDOSContains(array(
			array('ClassName' => 'PurchaseRequest'),
			array('ClassName' => 'PurchaseError')
		), $payment->Messages());
	}

	public function testOffSitePurchase() {
		$payment = $this->payment->setGateway('PaymentExpress_PxPay');
		$service = new PurchaseService($payment);
		$this->setMockHttpResponse('PaymentExpress/Mock/PxPayPurchaseSuccess.txt');//add success mock response from file
		$response = $service->purchase();
		$this->assertFalse($response->isSuccessful()); //payment has not been captured
		$this->assertTrue($response->isRedirect());
		$this->assertSame(
			'https://sec.paymentexpress.com/pxpay/pxpay.aspx?userid=Developer&request=v5H7JrBTzH-4Whs__1iQnz4RGSb9qxRKNR4kIuDP8kIkQzIDiIob9GTIjw_9q_AdRiR47ViWGVx40uRMu52yz2mijT39YtGeO7cZWrL5rfnx0Mc4DltIHRnIUxy1EO1srkNpxaU8fT8_1xMMRmLa-8Fd9bT8Oq0BaWMxMquYa1hDNwvoGs1SJQOAJvyyKACvvwsbMCC2qJVyN0rlvwUoMtx6gGhvmk7ucEsPc_Cyr5kNl3qURnrLKxINnS0trdpU4kXPKOlmT6VacjzT1zuj_DnrsWAPFSFq-hGsow6GpKKciQ0V0aFbAqECN8rl_c-aZWFFy0gkfjnUM4qp6foS0KMopJlPzGAgMjV6qZ0WfleOT64c3E-FRLMP5V_-mILs8a',
			$response->getRedirectURL());
		$this->assertSame("Authorized", $payment->Status);
		//... user would normally be redirected to external gateway at this point ...
		//mock complete purchase response
		$this->setMockHttpResponse('PaymentExpress/Mock/PxPayCompletePurchaseSuccess.txt');
		//mock the 'result' get variable into the current request
		$this->getHttpRequest()->query->replace(array('result' => 'abc123'));
		$response = $service->completePurchase();
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

	public function testFailedOffSitePurchase() {
		$payment = $this->payment->setGateway('PaymentExpress_PxPay');
		$service = new PurchaseService($payment);
		$this->setMockHttpResponse('PaymentExpress/Mock/PxPayPurchaseFailure.txt');//add success mock response from file
		$response = $service->purchase();
		$this->assertFalse($response->isSuccessful()); //payment has not been captured
		$this->assertFalse($response->isRedirect()); //redirect won't occur, because of failure
		$this->assertSame("Created", $payment->Status);

		//check messaging
		$this->assertDOSContains(array(
			array('ClassName' => 'PurchaseRequest'),
			array('ClassName' => 'PurchaseError'),
		), $payment->Messages());

		//TODO: fail in various ways
		$this->markTestIncomplete();
	}

	public function testFailedOffSiteCompletePurchase() {
		$this->setMockHttpResponse(
			'PaymentExpress/Mock/PxPayCompletePurchaseFailure.txt'
		);
		//mock the 'result' get variable into the current request
		$this->getHttpRequest()->query->replace(array('result' => 'abc123'));
		//mimic a redirect or request from offsite gateway
		$response = $this->get("paymentendpoint/UNIQUEHASH23q5123tqasdf/complete");
		//redirect works
		$headers = $response->getHeaders();
		$this->assertEquals(
			Director::baseURL()."shop/incomplete", 
			$headers['Location'],
			"redirected to shop/incomplete"
		);
		$payment = Payment::get()
					->filter('Identifier', 'UNIQUEHASH23q5123tqasdf')
					->first();
		$this->assertDOSContains(array(
			array('ClassName' => 'PurchaseRequest'),
			array('ClassName' => 'PurchaseRedirectResponse'),
			array('ClassName' => 'CompletePurchaseRequest'),
			array('ClassName' => 'CompletePurchaseError')
		), $payment->Messages());
	}


	public function testNonExistantGateway() {
		//exception when trying to run functions that require a gateway
		$payment = $this->payment;
		$service = PurchaseService::create(
				$payment->init("PxPayGateway", 100, "NZD")
			)->setReturnUrl("complete");

		$this->setExpectedException("RuntimeException");
		try{
		$result = $service->purchase();
		}catch(RuntimeException $e){
			$this->markTestIncomplete();
		$totalNZD = Payment::get()->filter('MoneyCurrency', "NZD")->sum();
		$this->assertEquals(27.23, $totalNZD);
		$service->purchase();
		$service->completePurchase();
			//just to assert that exception is thrown
			throw $e;
		}
	}

}
