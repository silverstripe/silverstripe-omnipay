<?php

/**
 * Payment model
 */
class Payment extends DataObject{

	private static $db = array(
		'Gateway' => 'Varchar(50)', //this is the omnipay 'short name'
		'Amount' => 'Money',
		'Status' => "Enum('Created,Authorized,Captured,Refunded,Void','Created')"
	);

	private static $has_one = array(
		"PaidBy" => "Member"
	);

	private static $has_many = array(
		"Transactions" => "PaymentTransaction"
	);

	/**
	 * Get the available configured payment types, with i18n readable names.
	 */
	public static function get_supported_gateways() {
		$allowed = Config::inst()->forClass('Payment')->allowed_gateways;
		if(empty($allowed)){
			$allowed = Omnipay\Common\GatewayFactory::find();
		}
		$allowed = array_combine($allowed, $allowed);
		$allowed = array_map(function($name) {
			return _t(
				"Payment.".strtoupper($name),
				Omnipay\Common\GatewayFactory::create($name)->getName()
			);
		}, $allowed);
		return $allowed;
	}

	/**
	 * Create a new payment model
	 */
	public static function create_payment($amount, $currency, $gateway){
		//TODO: user error/exception if gateway doesn't exist
		//TODO: generate a unique identifier string
		$payment = new Payment(array(
			'Gateway' => $gateway,
			'AmountAmount' => $amount,
			'AmountCurrency' => $currency,
			'Status' => 'Created'
		));
		$payment->write();
		return $payment;
	}
	
	/**
	 * Wrap the omnipay purchase function
	 */
	public function purchase($system, $customer){

		$card = new Omnipay\Common\CreditCard($customer);

		//TODO: store application's return/cancel urls for later use
		$this->setRedirectUrl($system['returnURL']);
		
		$transaction = $this->createTransaction('Purchase');

		$response = $this->oGateway()->purchase(array(
			'card' => $card,
			'amount' => $this->AmountAmount,
			'currency' => $this->AmountCurrency,
			'transactionId' => $this->ID,
			//'clientIp' => $controller->getIP(), //TODO: get the ip from somewhere
			'returnUrl' => PaymentController::get_return_url($transaction),
			'cancelUrl' => PaymentController::get_return_url($transaction,'cancel')
		))->send();
		
		//TODO: log request / response
		
		$transaction->Message = $response->getMessage();
		$transaction->Code = $response->getCode();
		$transaction->Reference = $response->getTransactionReference();
		$transaction->write();
		
		if ($response->isSuccessful()) {
			$this->Status = 'Captured';
			//TODO: save other things? Transaction reference
			$this->write();
		} elseif ($response->isRedirect()) { // redirect to off-site payment gateway
			//$this->Status = 'Authorized'; ...or 'Pending'?
		} else {
			//something went wrong
			//record
		}


		return $response;
	}

	public function authorize($parameters, $data){

	}

	public function capture(){
		//$transaction = $this->createTransaction();
	}

	public function refund(){

	}

	public function void(){

	}

	private function setRedirectUrl($returnurl){
		Session::set("Payment.ReturnUrl", $returnurl);
		//TODO: link to transaction/payment reference also?
	}

	private function createTransaction($type){
		$transaction = new PaymentTransaction(array(
			"Type" => $type,
			"PaymentID" => $this->ID
		));
		$transaction->write();
		return $transaction;
	}

	/**
	 * Get the omnipay gateway associated with this payment,
	 * with configuration applied.
	 */
	private function oGateway(){
		$gateway = Omnipay\Common\GatewayFactory::create($this->Gateway);
		$parameters = Config::inst()->forClass('Payment')->parameters;
		if(isset($parameters[$this->Gateway])){
			$gateway->initialize($parameters[$this->Gateway]);
		}
		return $gateway;
	}


}