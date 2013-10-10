<?php

class PaymentGatewayControllerTest extends FunctionalTest{

	static $fixture_file = array(
		'payment.yml'
	);

	protected $autoFollowRedirection = false;
	
	function setUp(){
		parent::setUp();

		Config::inst()->update("Payment", "allowed_gateways", array(
			'PayPal_Express',
			'PaymentExpress_PxPay',
			'Manual',
			'Dummy'
		));

	}

	function testReturnUrlGeneration() {
		$transaction = $this->objFromFixture('GatewayTransaction','transaction1');
		$url = PaymentGatewayController::get_return_url($transaction,'action',"shop/complete");
		$this->assertEquals(
			Director::absoluteURL("paymentendpoint/UNIQUEHASH23q5123tqasdf/action/c2hvcC9jb21wbGV0ZQ%3D%3D"),
			$url,
			"generated url"
		);
	}

	function testSucessfulEndpoint() {
		//Note the string 'c2hvcC9jb21wbGV0ZQ%3D%3D' is just "shop/complete" base64 encoded, then url encoded
		$response = $this->get("paymentendpoint/UNIQUEHASH23q5123tqasdf/complete/c2hvcC9jb21wbGV0ZQ%3D%3D"); //mimic gateway update

		$transaction = GatewayTransaction::get()
						->filter('Identifier','UNIQUEHASH23q5123tqasdf')
						->First();

		//model is appropriately updated
		//redirect works
	}

	function testSecurity() {
		//$this->get(); //mimic mallicious activity
		//incorrect security token
		//
		//database changes shouldn't be made by unauthorised means
		//see https://github.com/burnbright/silverstripe-omnipay/issues/13
	}

}
