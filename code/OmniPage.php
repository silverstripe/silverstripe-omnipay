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
		//Debug::show(Config::inst()->forClass('Payment')->parameters);
		return array(
			'Title' => 'Make a payment',
			'Content' => 'Make a payment using the following form'
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

	public function submit($data, $form){
		$name = $this->request->postVar("Gateway");
		$gateway = Omnipay\Common\GatewayFactory::create($name);
		$configs = Config::inst()->forClass('Payment')->parameters;

		$amount = $form->Fields()->fieldByName('Amount')->dataValue();

		$gateway->initialize();
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
			'lastName' => 'Bloggs',
			'number' => '4111111111111112',
			'expiryMonth' => '05',
			'expiryYear' => '14',
			'cvv' => '508'
		));

		//TODO: this URL isn't the best...we want to update the model after returning
		$returnUrl = Controller::join_links($this->Link(),'complete',$payment->ID);
		$cancelUrl = $returnUrl;
		$clientIp = $this->request->getIP();

		$response = $gateway->purchase(
			array(
				'card' => $card,
				//'token' => $token, //TODO
				'amount' => $amount,
				'currency' => $currency,
				//'description' => $description, //TODO
				//'transactionId' => $transactionid, //TODO
				'clientIp' => $clientip,
				'returnUrl' => $returnUrl,
				'cancelUrl' => $cancelUrl
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

			//TODO: where is the best place to go on failure?
			// payment failed: display message to customer
			return array(
				'Title' => 'Error',
				'Form' => $response->getMessage()
			);
		}

		return;
	}

	public function complete(){

		//TODO: get payment data etc, if allowed
		$payment = Payment::get()->byID($this->request->param('ID'));

		Debug::show($payment);
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
