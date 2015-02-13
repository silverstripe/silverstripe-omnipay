<?php
if(class_exists("Page_Controller")){
class PaymentControllerTest extends PaymentTest{

	protected $extraDataObjects = array(
		"PaymentControllerTest_Payable"
	);

	public function setUpOnce(){
		//add reverse has_one relation to Payment for Payable
		Payment::config()->has_one = array(
			"Payable" => "PaymentControllerTest_Payable"
		);
		parent::setUpOnce();
	}

	protected function getController(){
		$payable = new PaymentControllerTest_Payable();
		$payable->write();
		$parent = new Page_Controller(new Page(array(
			'URLSegment' => 'test'
		)));

		return new PaymentController($parent, "payment", $payable, $payable->Cost);
	}

	public function testSettersGetters(){
		$controller = $this->getController();

		$controller->setSuccessURL("x");
		$this->assertEquals("x", $controller->getSuccessURL());
		$controller->setCancelURL("x");
		$this->assertEquals("x", $controller->getCancelURL());
		$this->assertFalse($controller->isPaid());

		$controller->setCurrency("GBP");
		$this->assertEquals("GBP", $controller->getCurrency());

		$this->assertEquals(100, $controller->getAmount());

		$this->assertInstanceOf("PaymentControllerTest_Payable", $controller->getPayable());
	}

	public function testIndex() {
		$controller = $this->getController();

		//default is dataform
		$output = $controller->index();
		$this->assertInstanceOf("Form", $output['Form']);
		$this->assertEquals("GatewaySelectForm", $output['Form']->getName());

		//start a payment
		$newpayment = Payment::create()->init("Dummy", 100, "NZD");
		$controller->getPayable()->Payments()->add($newpayment);

		//index switches to data form
		$output = $controller->index();
		$this->assertInstanceOf("Form", $output['Form']);
		$this->assertEquals("GatewayDataForm", $output['Form']->getName());
	}

	public function testGatewaySelection() {
		$controller = $this->getController();
		$form = $controller->GatewaySelectForm();
	}

	public function testCancelPayment() {
		//$controller->cancel();
		$this->markTestIncomplete();
	}

	/**
	 * Test payment via omnipay
	 */
	public function testPayment() {

		$controller = $this->getController();
		$payable = $controller->getPayable();
		//set current payment

		$data = array(
			'action_pay' => 'Make Payment'
		);
		$form = $controller->GatewayDataForm();
		$form->loadDataFrom($data);
		$controller->pay($data, $form);
		
		$this->assertEquals(100, $payable->TotalPaid());
	}
	
}

class PaymentControllerTest_Payable extends DataObject implements TestOnly{

	protected $customdata = array();

	private static $extensions = array(
		"Payable"
	);

	public function setCustomData($customdata) {
		$this->customdata = $customdata;
	}

	public function getCost(){
		return 100;
	}

	public function getOmnipayMappedData() {
		return $this->customdata;
	}

}
}