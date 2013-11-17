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

use Omnipay\Common\GatewayFactory;
use Omnipay\Common\CreditCard;
use Omnipay\Common\Message\AbstractResponse;

final class Payment extends DataObject{

	private static $db = array(
		'Gateway' => 'Varchar(50)', //this is the omnipay 'short name'
		'Money' => 'Money', //contains Amount and Currency
		'Status' => "Enum('Created,Authorized,Captured,Refunded,Void','Created')"
	);

	private static $has_many = array(
		'Transactions' => 'PaymentMessage'
	);
	
	private static $defaults = array(
		'Status' => 'Created'
	);

	private static $casting = array(
		"Amount" => "Decimal"
	);

	private static $summary_fields = array(
		'Money',
		'Gateway',
		'Status',
		'Created'
	);

	private $returnurl, $cancelurl, $httpclient, $httprequest;

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
				GatewayFactory::create($name)->getName()
			);
		}, $allowed);

		return $allowed;
	}

	public function getCMSFields() {
		$fields = parent::getCMSFields()->makeReadonly();
		$transactions = $fields->addFieldToTab("Root.Transactions",
			GridField::create("Transactions","Transactions", $this->Transactions(),
				new GridFieldConfig_RecordViewer()
			)
		);
		
		return $fields;
	}

	/**
	 * Change search context to use a dropdown for list of gateways.
	 */
	public function getDefaultSearchContext(){
		$context = parent::getDefaultSearchContext();
		$fields = $context->getSearchFields();
		$fields->removeByName('Gateway');
		$fields->insertBefore(DropdownField::create('Gateway','Gateway',
			Payment::get_supported_gateways()
		)->setHasEmptyDefault(true),'Status');
		$fields->fieldByName('Status')->setHasEmptyDefault(true);

		return $context;
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

	public function getTitle() {
		return implode(' ',array(
			$this->forTemplate()->Nice(),
			$this->dbObject('Created')->Date()
		));
	}

	/**
	 * Set the payment gateway
	 * @param string $gateway the omnipay gateway short name.
	 * @return Payment this object for chaining
	 */
	public function setGateway($gateway) {
		if($this->Status == 'Created'){
			$this->setField('Gateway', $gateway);	
		}
		return $this;
	}

	/**
	 * Get the payment amount
	 * @return string amount of this payment
	 */
	public function getAmount() {
		return $this->MoneyAmount;
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

	/**
	 * Get just the currency of this payment's money component
	 * @return string the currency of this payment
	 */
	public function getCurrency() {
		return $this->MoneyCurrency;
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
	 * Get the url to return to, that has been previously stored.
	 * This is not a database field.
	 * @return string the url
	 */
	public function getReturnUrl() {
		return $this->returnurl;
	}

	/**
	 * Set the url to redirect to after payment is made/attempted.
	 * This function also populates the cancel url, if it is empty.
	 * @return Payment this object for chaining
	 */
	public function setReturnUrl($url) {
		$this->returnurl = $url;
		if(!$this->cancelurl){
			$this->cancelurl = $url;
		}
		return $this;
	}

	public function getCancelUrl() {
		return $this->cancelurl;
	}

	/**
	 * Set the url to redirect to after payment is cancelled
	 * @return Payment this object for chaining
	 */
	public function setCancelUrl($url) {
		$this->cancelurl = $url;
		return $this;
	}

	/**
	 * This payment requires no more processing.
	 * @return boolean completion
	 */
	public function isComplete(){
		return $this->Status == 'Captured' ||
				$this->Status == 'Refunded' ||
				$this->Status == 'Void';
	}

	public function forTemplate(){
		return $this->dbObject('Money');
	}

	/**
	 * Get the omnipay gateway associated with this payment,
	 * with configuration applied.
	 * 
	 * @return AbstractGateway omnipay gateway class
	 */
	public function oGateway(){
		$gateway = GatewayFactory::create($this->Gateway, $this->httpclient, $this->httprequest);
		$parameters = Config::inst()->forClass('Payment')->parameters;
		if(isset($parameters[$this->Gateway])) {
			$gateway->initialize($parameters[$this->Gateway]);
		}

		return $gateway;
	}

	/**
	 * Attempt to make a payment
	 * @param  array $data returnUrl/cancelUrl + customer creditcard and billing/shipping details.
	 * @return ResponseInterface omnipay's response class, specific to the chosen gateway.
	 */
	public function purchase($data = array()) {
		if($this->Status !== "Created"){
		 	return null; //could be handled better? send payment response?
		}

		//force write
		if(!$this->isInDB()){
			$this->write();
		}

		$this->returnurl = isset($data['returnUrl']) ? $data['returnUrl'] : $this->returnurl;
		$this->cancelurl = isset($data['cancelUrl']) ? $data['cancelUrl'] : $this->cancelurl;

		$transaction = $this->createTransaction('Purchase'); //rename?
		$request = $this->oGateway()->purchase(array(
			'card' => new CreditCard($data),
			'amount' => (float)$this->MoneyAmount,
			'currency' => $this->MoneyCurrency,
			'transactionId' => $transaction->ID,
			'clientIp' => isset($data['clientIp']) ? $data['clientIp'] : null,
			'returnUrl' => PaymentGatewayController::get_return_url($transaction, 'complete', $this->returnurl),
			'cancelUrl' => PaymentGatewayController::get_return_url($transaction,'cancel', $this->cancelurl)
		));
		$this->logRequest($request);
		//TODO: add database log entry - success or failed purchase request
		$response = $request->send();
		$this->logResponse($response);
		$this->completeTransaction($transaction, $response);
		//TODO: add database log entry - success or failed purchase response
		
		//update payment model
		if ($response->isSuccessful()) {
			$this->Status = 'Captured';
			$this->write();
		} elseif ($response->isRedirect()) { // redirect to off-site payment gateway
			$this->Status = 'Authorized'; //or should this be 'Pending'?
			$this->write();
		} else {
			//TODO: something went wrong...record this. Update payment and/or transaction?
		}

		return new GatewayResponse($response, $this);
	}

	/**
	 * Finalise this payment, after off-site external processing.
	 * This is ususally only called by PaymentGatewayController.
	 * @return PaymentResponse encapsulated response info
	 */
	public function completePurchase(){
		//TODO: do we care if gateway isn't set, or doesn't exist?
		
		$transaction = $this->createTransaction('CompletePurchase');
		$request = $this->oGateway()->completePurchase(array(
			'amount' => (float)$this->MoneyAmount
		));

		//Debug::show($request);

		$this->logRequest($request);
		//TODO: add database log entry - success or failed purchase request

		try{
			$response = $request->send();
			
			$this->completeTransaction($transaction, $response);

			$this->logResponse($response);

			if($response->isSuccessful()){
				$this->Status = 'Captured';
				$this->write();
			}
			
			//TODO: add database log entry - success or failed purchase response

		} catch (\Exception $e) {
			//TODO: log failure?
			//var_dump($request);
			throw $e;
		}
		
		return new GatewayResponse($response, $this);
	}

	/**
	 * Initiate the authorisation process for on-site and off-site gateways.
	 * @param  array $data returnUrl/cancelUrl + customer creditcard and billing/shipping details.
	 * @return ResponseInterface omnipay's response class, specific to the chosen gateway.
	 */
	public function authorize($data = array()) {
		//TODO
	}

	/**
	 * Complete authorisation, after off-site external processing.
	 * This is ususally only called by PaymentGatewayController.
	 * @return PaymentResponse encapsulated response info
	 */
	public function completeAuthorize() {
		//TODO
	}

	/**
	 * Do the capture of money on authorised credit card. Money exchanges hands.
	 * @return PaymentResponse encapsulated response info
	 */
	public function capture() {
		//TODO
	}

	/**
	 * Return money to the previously charged credit card.
	 * @return PaymentResponse encapsulated response info
	 */
	public function refund() {
		//TODO
	}

	/**
	 * Cancel this payment, and prevent any future changes.
	 * @return PaymentResponse encapsulated response info
	 */
	public function void() {
		//TODO: call gateway function, if available
		$this->Status = "Void";
		$this->write();
	}

	/**
	 * Record a transaction on this for this payment.
	 * @param  string $type the type of transaction to create
	 * @return GatewayTransaction newly created dataobject, saved to database.
	 */
	private function createTransaction($type){
		$transaction = new GatewayMessage(array(
			"Type" => $type,
			"PaymentID" => $this->ID,
			"Gateway" => $this->Gateway
		));
		$transaction->generateIdentifier();
		$transaction->write();

		return $transaction;
	}

	/**
	 * Record the gateway response for a given transaction object.
	 * @param  GatewayTransaction $transaction the transaction to complete
	 * @param  AbstractResponse   $response    the response object to complete the transaction with
	 * @return GatewayTransaction
	 */
	private function completeTransaction(GatewayMessage $transaction, AbstractResponse $response){
		$transaction->update(array(
			'Message' => $response->getMessage(),
			'Code' => $response->getCode(),
			'Reference' => $response->getTransactionReference(),
			'Success' => $response->isSuccessful()
		));
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

	//testing functions (could these instead be injected somehow?)

	/**
	 * Set the guzzle client (for testing)
	 * @param GuzzleHttpClientInterface $httpClient [description]
	 */
	public function setHTTPClient(Guzzle\Http\ClientInterface $httpClient){
		$this->httpclient = $httpClient;

		return $this;
	}

	/**
	 * Set the symphony http request (for testing)
	 * @param SymfonyComponentHttpFoundationRequest $httpRequest [description]
	 */
	public function setHTTPRequest(Symfony\Component\HttpFoundation\Request $httpRequest){
		$this->httprequest = $httpRequest;

		return $this;
	}

}