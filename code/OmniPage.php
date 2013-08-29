<?php

class OmniPage_Controller extends Page_Controller{

	private static $allowed_actions = array(
		'Form',
		'complete'
	);

	public function Link($action = null) {
		return Controller::join_links('omni',$action);
	}

	public function index(){
		return array(
			'Title' => 'Make a payment'
		);
	}

	private function gateways(){
		$allowed = Config::inst()->forClass('Payment')->allowed_gateways;
		if(empty($allowed)){
			$allowed = Omnipay\Common\GatewayFactory::find();
		}
		$allowed = array_combine($allowed, $allowed);
		$allowed = array_map(function($name) {
	        return Omnipay\Common\GatewayFactory::create($name)->getName();
	    }, $allowed);
		return $allowed;
	}

	public function Form(){
		$fields =  new FieldList(
			CurrencyField::create("Amount","Amount",20),
			DropdownField::create("Gateway","Gateway",$this->gateways())
		);
		$actions = new FieldList(
			FormAction::create("submit")
		);
		return new Form($this,"Form",$fields,$actions);
	}

	public function submit(){
		$name = $this->request->postVar("Gateway");
		$gateway = Omnipay\Common\GatewayFactory::create($name);
		$configs = Config::inst()->forClass('Payment')->parameters;
		$gateway->initialize($configs[$name]);
		//$settings = $gateway->getParameters();
		
		$payment = $this->createPayment(
			$name,
			(float)$this->request->postVar('Amount'), //TODO: sanitize
			'NZD'
		);

		//TODO: do actual payment
		
		$card = new Omnipay\Common\CreditCard();
		$card->initialize(array(
			'firstName' => 'Joe',
			'lastName' => 'Bloggs'
		));

		$returnUrl = Controller::join_links($this->Link(),'complete',$payment->ID);
		//TODO: this isn't the best...we want to update the model after returning

		$response = $gateway->purchase(
			array(
				'amount' => $this->request->postVar('Amount'),
				'currency' => 'USD',
				'card' => $card,
				'returnUrl' => $returnUrl
			)
		)->send();

		//TODO: store response data in payment model?

		if ($response->isSuccessful()) {
			// payment was successful: update database
			//print_r($response);
			$this->redirect($returnUrl);
			return;

		} elseif ($response->isRedirect()) {
			// redirect to offsite payment gateway
			//$response->redirect();
			$this->redirect($response->getRedirectUrl()); //ss redirect
			return;

		} else {
			// payment failed: display message to customer
			return array(
				'Title' => 'Error',
				'Content' => $response->getMessage()
			);
		}

		return;
	}

	public function complete(){

		//TODO: get payment data etc, if allowed
		$payment = Payment::get()->byID($this->request->param('ID'));

		//stub
		return array(
			'Title' => 'Payment Complete',
			'Content' => '',
			'Form' => '<a href="'.$this->Link().'">make another payment</a>'
		);
	}

	private function createPayment($gateway, $amount, $currency){
		$payment = new Payment(array(
			'Gateway' => $gateway, //TODO: require gateway?
			'Amount' => array('Amount' => $amount, 'Currency' => $currency),
			'PaidByID' => Member::currentUserID()
		));
		$payment->write();
		return $payment;
	}

}
