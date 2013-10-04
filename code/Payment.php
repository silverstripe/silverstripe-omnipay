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
final class Payment extends DataObject{

	private static $db = array(
		'Gateway' => 'Varchar(50)', //this is the omnipay 'short name'
		'Money' => 'Money', //contains Amount and Currency
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
	 * Set gateway, amount, and currency in one function.
	 * @param  string $gateway   omnipay gateway short name
	 * @param  float $amount     monetary amount
	 * @param  string $currency the currency to set
	 * @return  Payment this object for chaining
	 */
	public function init($gateway, $amount, $currency) {
		$this->setGateway($gateway);
		$this->setAmount($amount);
		$this->setCurrency($currency);
		return $this;
	}

	/**
	 * Set the payment amount, but only when the status is 'Created'.
	 * @param float $amt value to set the payment to
	 * @return  Payment this object for chaining
	 */
	public function setAmount($amount) {
		if($amount instanceof Money) {
			$this->setField("Money",$amount);
		} elseif($this->Status == 'Created' && is_numeric($amount)) {
			$this->MoneyAmount = $amount;
		}
		return $this;
	}

	public function getAmount() {
		return $this->MoneyAmount;
	}

	/**
	 * Set the payment currency, but only when the status is 'Created'.
	 * @param string $currency the currency to set
	 */
	public function setCurrency($currency) {
		if($this->Status == 'Created') {
			$this->MoneyCurrency = $currency;
		}

		return $this;
	}

	/**
	 * Get just the currency of this payment's money component
	 * @return string the currency of this payment
	 */
	public function getCurrency() {
		return $this->MoneyCurrency;
	}

	/**
	 * Set the payment gateway
	 * @param string $gateway the omnipay gateway short name.
	 * @return Payment this object for chaining
	 */
	public function setGateway($gateway) {
		$this->setField('Gateway', $gateway);
		return $this;
	}

	/**
	 * Get the omnipay gateway associated with this payment,
	 * with configuration applied.
	 * 
	 * @return AbstractGateway omnipay gateway class
	 */
	public function oGateway(){
		$gateway = Omnipay\Common\GatewayFactory::create($this->Gateway);
		$parameters = Config::inst()->forClass('Payment')->parameters;
		if(isset($parameters[$this->Gateway])) {
			$gateway->initialize($parameters[$this->Gateway]);
		}

		return $gateway;
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
			'amount' => (float)$this->MoneyAmount,
			'currency' => $this->MoneyCurrency,
			'transactionId' => $transaction->ID,
			'clientIp' => isset($system['clientIp']) ? $system['clientIp'] : null,
			'returnUrl' => PaymentController::get_return_url($transaction, 'complete', isset($system['returnUrl']) ? $system['returnUrl'] : null),
			'cancelUrl' => PaymentController::get_return_url($transaction,'cancel', isset($system['cancelUrl']) ? $system['cancelUrl'] : null)
		));
		$this->logRequest($request);
		$response = $request->send();
		$this->logResponse($response);
		
		$transaction->update(array(
			'Message' => $response->getMessage(),
			'Code' => $response->getCode(),
			'Reference' => $response->getTransactionReference()
		));
		$transaction->write();
		
		if ($response->isSuccessful()) {
			$this->Status = 'Captured';
			$this->write();
		} elseif ($response->isRedirect()) { // redirect to off-site payment gateway
			$this->Status = 'Authorized'; //or should this be 'Pending'?
			$this->write();
		} else {
			//something went wrong...record this. Update payment and/or transaction?
		}

		return $response;
	}

	/**
	 * Finalise this payment, after external processing.
	 * This is ususally only called by PaymentController
	 * @return [type] [description]
	 */
	public function completePurchase(){
		$gateway = $payment->oGateway();
		if($gateway && $gateway->supportsCompletePurchase()){
			$request = $gateway->completePurchase();
			$this->logRequest($request);
			$response = $request->send();
			$this->logResponse($response);
			//TODO: update model
		}
		return $response;
	}

	public function authorize($system, $customer) {
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
	 * Create a new transaction model for this payment
	 * @param  string $type the type of transaction to create
	 * @return PaymentTransaction Newly created dataobject, saved to database.
	 */
	private function createTransaction($type){
		$transaction = new PaymentTransaction(array(
			"Type" => $type,
			"PaymentID" => $this->ID
		));
		$transaction->generateIdentifier();
		$transaction->write();

		return $transaction;
	}

	/**
	 * Helper function for logging gateway requests
	 * @param  AbstractRequest $request the omnipay request object
	 */
	private function logRequest($request){
		if((bool)Config::inst()->get('Payment','file_logging')){
			$parameters = $request->getParameters();
			//TODO: omfuscate, or remove the creditcard details from logging
			Debug::log($this->Gateway." REQUEST\n\n".print_r($parameters,true));
		}
	}

	/**
	 * Helper function for logging gateay responses
	 * @param  AbstractResponse $response the omnipay response object
	 */
	private function logResponse($response){
		if((bool)Config::inst()->get('Payment','file_logging')){
			Debug::log($this->Gateway." RESPONSE\n\n".print_r(array(
				'Data' => $response->getData(),
				'isRedirect' => $response->isRedirect(),
			),true));
		}
	}

}