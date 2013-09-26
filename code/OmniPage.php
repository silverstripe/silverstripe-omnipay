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
		return new PaymentForm($this,"Form",$fields,$actions);
	}

	public function submit($data, $form){

		$data = $form->getData(); //get clean data
		$amount = $data['Amount'];
		$gatewayname = $data['Gateway'];

		$gateway = Omnipay\Common\GatewayFactory::create($gatewayname);
		$parameters = Config::inst()->forClass('Payment')->parameters; //get the yaml configuration
		$gateway->initialize($parameters); //init

		$card = new Omnipay\Common\CreditCard($data);
		
		$payment = $this->createPayment($gatewayname, $amount, 'NZD');

		//TODO: this URL isn't the best...we want to update the model after returning
		$returnUrl = Director::absoluteURL(
			Controller::join_links($this->Link(),'complete',$payment->ID)
		);
		$cancelUrl = Director::absoluteURL($this->Link());

		//do the payment
		Debug::show($payment);
		$response = $gateway->purchase(
			array(
				'card' => $card,
				//'token' => $token, //TODO: allow paying with a stored card
				'amount' => $payment->AmountAmount,
				'currency' => $payment->AmountCurrency,
				//'description' => $description, //TODO: what is this for?
				'transactionId' => $payment->ID,
				'clientIp' => $this->request->getIP(),
				'returnUrl' => $returnUrl,
				'cancelUrl' => $cancelUrl
			)
		)->send();

		//TODO: store response data in payment model?
		//$payment->Data = $response->getData();

		if ($response->isSuccessful()) {
			//TODO: update payment model status
			//$payment->complete();
			$this->redirect($returnUrl); // payment was successful
			return;

		} elseif ($response->isRedirect()) { // redirect to off-site payment gateway
			$this->redirect($response->getRedirectUrl());
			return;

		} else { // payment failed: display message to customer
			//TODO: where is the best place to go on failure?
			$form->sessionMessage($response->getMessage());
			$this->redirectBack();
		}

		return;
	}

	public function complete(){

		//TODO: get payment data etc, if allowed
		$payment = Payment::get()->byID($this->request->param('ID'));

		//TODO: do post-redirect handling
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
			'AmountAmount' => $amount,
			'AmountCurrency' => $currency,
			'PaidByID' => Member::currentUserID()
		));
		$payment->write();
		return $payment;
	}

}
