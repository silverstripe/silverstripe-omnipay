<?php

/**
 * Payment
 */
class Payment extends DataObject{

	private static $db = array(
		'Gateway' => 'Varchar(50)', //omnipay 'short name'
		'Amount' => 'Money',
		'Status' => "Enum('Created,Authorized,Captured,Refunded,Void','Created')"
		//Token?
		//Data?
	);

	private static $has_one = array(
		"PaidBy" => "Member"
	);

	/**
	 * Get the available configured payment types
	 */
	public static function get_supported_methods() {
		return Config::inst()->forClass('Payment')->allowed_gateways; //TODO: supply i18n / human friendly names...or supply all?
	}

	/**
	 * Only allow setting amount when payment status is 'Created'
	 */
/*	public setAmount($amount){
		if($this->Status == 'Created'){
			$this->Amount = $amount;
		}
	}*/

	//
	public function authorize($parameters, $data){

		//find gateway
		$gateway = $this->oGateway();

		//input customer data into Credit card
		$data = array(
			// firstName
			// lastName
			// number
			// expiryMonth
			// expiryYear
			// startMonth
			// startYear
			// cvv
			// issueNumber
			// type
			// billingAddress1
			// billingAddress2
			// billingCity
			// billingPostcode
			// billingState
			// billingCountry
			// billingPhone
			// shippingAddress1
			// shippingAddress2
			// shippingCity
			// shippingPostcode
			// shippingState
			// shippingCountry
			// shippingPhone
			// company
			// email
		);
		
		//input system data into gateway
		$parameters = array(
			//card
			//token
			//amount
			//currency
			//description
			//transactionId
			//clientIp
			//returnUrl
			//cancelUrl
		);

		//send authorisation request

		//handle exceptions

		$gateway->authorize($parameters);
		//update status
		$this->Status = "Authorized";

		//TODO: how to handle redirect

	}

	public function capture(){

	}

	public function refund(){

	}

	public function void(){

	}

	/**
	 * Get the omnipay gateway associated with this payment,
	 * with configuration applied.
	 */
	protected function oGateway(){
		$gateway = Omnipay\Common\GatewayFactory::create($this->Gateway);
		$parameters = Config::inst()->forClass('Payment')->parameters;
		if(isset($parameters[$this->Gateway])){
			$gateway->initialize($parameters[$this->Gateway]);
		}
		return $gateway;
	}


}