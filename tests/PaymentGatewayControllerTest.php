<?php

class PaymentGatewayControllerTest extends PaymentTest{

	public static $fixture_file = array(
		'payment.yml'
	);

	public function testReturnUrlGeneration() {
		$transaction = $this->objFromFixture('GatewayMessage', 'message1');
		$url = PaymentGatewayController::get_return_url($transaction, 'action');
		$this->assertEquals(
			Director::absoluteURL("paymentendpoint/UNIQUEHASH23q5123tqasdf/action"),
			$url,
			"generated url"
		);
	}

	public function testCompleteEndpoint() {
		$this->setMockHttpResponse(
			'PaymentExpress/Mock/PxPayCompletePurchaseSuccess.txt'
		);
		//mock the 'result' get variable into the current request
		$this->getHttpRequest()->query->replace(array('result' => 'abc123'));
		//mimic a redirect or request from offsite gateway
		$response = $this->get("paymentendpoint/UNIQUEHASH23q5123tqasdf/complete");
		$message = GatewayMessage::get()
						->filter('Identifier', 'UNIQUEHASH23q5123tqasdf')
						->first();
		//redirect works
		$headers = $response->getHeaders();
		$this->assertEquals(
			Director::baseURL()."shop/complete", 
			$headers['Location'],
			"redirected to shop/complete"
		);
		$payment = $message->Payment();
		$this->assertDOSContains(array(
			array('ClassName' => 'PurchaseRequest'),
			array('ClassName' => 'PurchaseRedirectResponse'),
			array('ClassName' => 'CompletePurchaseRequest'),
			array('ClassName' => 'PurchasedResponse')
		), $payment->Messages());
	}

	public function testNotifyEndpoint() {
		$this->setMockHttpResponse(
			'PaymentExpress/Mock/PxPayCompletePurchaseSuccess.txt'
		);
		//mock the 'result' get variable into the current request
		$this->getHttpRequest()->query->replace(array('result' => 'abc123'));
		//mimic a redirect or request from offsite gateway
		$response = $this->get("paymentendpoint/UNIQUEHASH23q5123tqasdf/notify");
		$message = GatewayMessage::get()
						->filter('Identifier', 'UNIQUEHASH23q5123tqasdf')
						->first();
		//redirect works
		$this->assertNull($response->getHeader('Location'));
		$payment = $message->Payment();
		$this->assertDOSContains(array(
			array('ClassName' => 'PurchaseRequest'),
			array('ClassName' => 'PurchaseRedirectResponse'),
			array('ClassName' => 'CompletePurchaseRequest'),
			array('ClassName' => 'PurchasedResponse')
		), $payment->Messages());
	}

	public function testBadReturnURLs() {
		$response = $this->get("paymentendpoint/ASDFHSADFunknonwhash/complete/c2hvcC9jb2");
		$this->assertEquals(404, $response->getStatusCode());
	}

	public function testSecurity() {
		//$this->get(); //mimic mallicious activity
		//incorrect security token
		//
		//database changes shouldn't be made by unauthorised means
		//see https://github.com/burnbright/silverstripe-omnipay/issues/13
	}

	//this failed because gateaway passed identifier was $message->ID, not $message->Identifier
	//TODO: test purchase -> completePurchase
}
