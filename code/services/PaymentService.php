<?php

/**
 * Payment Service
 *
 * Provides wrapper methods for interacting with the omnipay gateways
 * library.
 * 
 * Interfaces with the omnipay library
 *
 * @package payment
 */

use Omnipay\Common\GatewayFactory;
use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\AbstractRequest;

abstract class PaymentService extends Object{

	private static $httpclient, $httprequest;

	protected $payment;
	protected $returnurl;
	protected $cancelurl;
	protected $response;

	public function __construct(Payment $payment) {
		$this->payment = $payment;
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
	 * Get the appropriate redirect url
	 */
	public function getRedirectURL(){
		if($this->response){
			if ($this->response->isSuccessful()) {
				return $this->getReturnUrl();
			} elseif ($this->response->isRedirect()) {
				return $this->response->getRedirectUrl();
			}
		}

		return $this->getCancelUrl();
	}

	/**
	 * Get the omnipay gateway associated with this payment,
	 * with configuration applied.
	 *
	 * @throws RuntimeException - when gateway doesn't exist.
	 * @return AbstractGateway omnipay gateway class
	 */
	public function oGateway() {
		$gateway = GatewayFactory::create(
			$this->payment->Gateway,
			self::$httpclient,
			self::$httprequest
		);
		$parameters = Config::inst()->forClass('Payment')->parameters;
		if(isset($parameters[$this->payment->Gateway])) {
			$gateway->initialize($parameters[$this->payment->Gateway]);
		}

		return $gateway;
	}	
	
	/**
	 * Record a transaction on this for this payment.
	 * @param string $type the type of transaction to create. 
	 *        This is any class that is (or extends) PaymentMessage.
	 * @param array|string|AbstractResponse $data the response to record, or data to store
	 * @return GatewayTransaction newly created dataobject, saved to database.
	 */
	protected function createMessage($type, $data = null) {
		$output = array(
			"PaymentID" => $this->payment->ID,
			"Gateway" => $this->payment->Gateway
		);
		if(is_string($data)){
			$output =  array_merge(array(
				'Message' => $data
			), $output);
		}if(is_array($data)){
			$output =  array_merge($data, $output);
		}elseif($data instanceof AbstractResponse){
			$output =  array_merge(array(
				"Message" => $data->getMessage(),
				"Code" => $data->getCode(),
				"Reference" => $data->getTransactionReference(),
				"Data" => $data->getData()
			), $output);
		}elseif($data instanceof AbstractRequest){
			$output =  array_merge(array(
				'Token' => $data->getToken(),
				'CardReference' => $data->getCardReference(),
				'Amount' => $data->getAmount(),
				'Amount' => $data->getAmount(),
				'Currency' => $data->getCurrency(),
				'Description' => $data->getDescription(),
				'TransactionId' => $data->getTransactionId(),
				'TransactionReference' => $data->getTransactionReference(),
				'ClientIp' => $data->getClientIp(),
				'ReturnUrl' => $data->getReturnUrl(),
				'CancelUrl' => $data->getCancelUrl(),
				'NotifyUrl' => $data->getNotifyUrl()
			), $output);
		}
		$this->logToFile($output, $type);
		$message = $type::create($output);
		if(method_exists($message,'generateIdentifier')){
			$message->generateIdentifier();
		}
		$message->write();
		$this->payment->Messages()->add($message);

		return $message;
	}

	/**
	 * Helper function for logging gateway requests
	 * @param  AbstractRequest $request the omnipay request object
	 */
	protected function logToFile($data, $type = "") {
		if((bool)Config::inst()->get('Payment', 'file_logging')){
			$logstyle = Config::inst()->get('Payment', 'file_logging');
			if($logstyle === "expanded"){
				Debug::log($type." (".$this->Gateway.")\n\n".
					print_r($data,true));
			}else{
				Debug::log(implode(",",array(
					$type,
					$this->Gateway,
					isset($data['Message']) ? $data['Message'] : " ",
					isset($data['Code']) ? $data['Code'] : " ",
				)));
			}
		}
	}

	protected function createGatewayResponse() {
		$gatewayresponse = new GatewayResponse($this->payment);
		$gatewayresponse->setRedirectURL($this->getRedirectURL());
		return $gatewayresponse;
	}

	//testing functions (could these instead be injected somehow?)

	/**
	 * Set the guzzle client (for testing)
	 * @param GuzzleHttpClientInterface $httpClient [description]
	 */
	public static function set_http_client(Guzzle\Http\ClientInterface $httpClient){
		self::$httpclient = $httpClient;
	}

	/**
	 * Set the symphony http request (for testing)
	 * @param SymfonyComponentHttpFoundationRequest $httpRequest [description]
	 */
	public static function set_http_request(Symfony\Component\HttpFoundation\Request $httpRequest){
		self::$httprequest = $httpRequest;
	}

}