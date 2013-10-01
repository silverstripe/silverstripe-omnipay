<?php

/**
 * Payment DataObject
 *
 * This class is used for storing a payment amount, and it's status of being
 * paid or not, and the gateway used to make payment.
 * It also provides wrapper methods for interacting with the omnipay gateways
 * library.
 *
 * @package payment
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
	
	private static $defaults = array(
		'Status' => 'Created'
	);

	/**
	 * Get the available configured payment types, with i18n readable names.
	 * @return array map of gateway short name to translated long name.
	 */
	public static function get_supported_gateways() {
		$allowed = Config::inst()->forClass('Payment')->allowed_gateways;
		$allowed = array_map(function($name) {
			return _t(
				"Payment.".strtoupper($name),
				Omnipay\Common\GatewayFactory::create($name)->getName()
			);
		}, $allowed);

		return $allowed;
	}

	/**
	 * Wrap the omnipay purchase function
	 * @param  array $system   returnUrl, cancelUrl
	 * @param  array $customer customer creditcard and billing/shipping details.
	 * @return ResponseInterface omnipay's response class, specific to the chosen gateway.
	 */
	public function purchase($system, $customer) {
		$card = new Omnipay\Common\CreditCard($customer);
		$transaction = $this->createTransaction('Purchase');

		$request = $this->oGateway()->purchase(array(
			'card' => $card,
			'amount' => $this->AmountAmount,
			'currency' => $this->AmountCurrency,
			'transactionId' => $transaction->ID,
			//'clientIp' => $controller->getIP(), //TODO: get the ip from somewhere
			'returnUrl' => PaymentController::get_return_url($transaction), //Add return url to get variable
			'cancelUrl' => PaymentController::get_return_url($transaction,'cancel') //Add return url to get variable
		));
		$response = $request->send();
		
		//TODO: log request / response to file
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

	public function authorize($parameters, $data) {
		//TODO
	}

	public function capture() {
		//TODO
	}

	public function refund() {
		//TODO
	}

	public function void() {
		//TODO
	}

	/**
	 * Set the payment amount, but only when the status is 'Created'.
	 * @param float $amt value to set the payment to
	 * @return  Payment this object for chaining
	 */
	public function setAmount($amount) {
		if($amount instanceof Money) {
			$this->dbObject("Amount")->setValue($amount);
		} elseif($this->Status == 'Created') {
			$this->AmountAmount = $amount;
		}

		return $this;
	}

	/**
	 * Set the payment currency, but only when the status is 'Created'.
	 * @param [type] $currency [description]
	 */
	public function setCurrency($currency) {
		if($currency instanceof Money) {
			$this->dbObject("Currency")->setValue($currency);
		} elseif($this->Status == 'Created') {
			$this->AmountCurrency = $currency;
		}

		return $this;
	}

	/**
	 * Set the payment gateway
	 * @param string $gateway the omnipay gateway short name.
	 * @return Payment this object for chaining
	 */
	public function setGateway($gateway) {
		$this->dbObject('Gateway')->setValue($gateway);

		return $this;
	}

	/**
	 * Create a new transaction model for this payment
	 * @param  string $type the type of transaction to create
	 * @return PaymentTransaction Newly created dataobject, saved to database.
	 */
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
	 * 
	 * @return AbstractGateway omnipay gateway class
	 */
	private function oGateway(){
		$gateway = Omnipay\Common\GatewayFactory::create($this->Gateway);
		$parameters = Config::inst()->forClass('Payment')->parameters;
		if(isset($parameters[$this->Gateway])) {
			$gateway->initialize($parameters[$this->Gateway]);
		}

		return $gateway;
	}

}