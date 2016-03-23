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

use Omnipay\Common\AbstractGateway;
use Omnipay\Common\GatewayFactory;
use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\AbstractRequest;

abstract class PaymentService extends Object
{
	/**
	 * @var Guzzle\Http\ClientInterface
	 */
	private static $httpclient;

	/**
	 * @var Symfony\Component\HttpFoundation\Request
	 */
	private static $httprequest;

	/**
	 * @var Payment
	 */
	protected $payment;

	/**
	 * @var String
	 */
	protected $returnurl;

	/**
	 * @var String
	 */
	protected $cancelurl;

	/**
	 * @var Guzzle\Http\Message\Response
	 */
	protected $response;

    /**
     * @var GatewayFactory
     */
	protected $gatewayFactory;

	private static $dependencies = array(
		'gatewayFactory' => '%$\Omnipay\Common\GatewayFactory',
	);


    /**
     * @param Payment
     */
	public function __construct(Payment $payment) {
		parent::__construct();
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
	 * @return PaymentService this object for chaining
	 */
	public function setReturnUrl($url) {
		$this->returnurl = $url;
		if (!$this->cancelurl) {
			$this->cancelurl = $url;
		}

		return $this;
	}

	/**
	 * @return string cancel url
	 */
	public function getCancelUrl() {
		return $this->cancelurl;
	}

	/**
	 * Set the url to redirect to after payment is cancelled
	 * @return PaymentService this object for chaining
	 */
	public function setCancelUrl($url) {
		$this->cancelurl = $url;

		return $this;
	}

	/**
	 * Get the appropriate redirect url
	 */
	public function getRedirectURL() {
		if ($this->response) {
			if ($this->response->isSuccessful()) {
				return $this->getReturnUrl();
			} elseif ($this->response->isRedirect()) {
				return $this->response->getRedirectUrl();
			}
		}

		return $this->getCancelUrl();
	}

	/**
	 * Update class properties via array.
	 */
	public function update($data) {
		if(isset($data['returnUrl'])){
			$this->setReturnUrl($data['returnUrl']);
		}
		if(isset($data['cancelUrl'])){
			$this->setCancelUrl($data['cancelUrl']);
		}
	}


	/**
	 * Get the omnipay gateway associated with this payment,
	 * with configuration applied.
	 *
	 * @throws RuntimeException - when gateway doesn't exist.
	 * @return AbstractGateway omnipay gateway class
	 */
	public function oGateway() {
        $gatewayName = $this->payment->Gateway;
		$gateway = $this->getGatewayFactory()->create(
            $gatewayName,
			self::$httpclient,
			self::$httprequest
		);

		$parameters = GatewayInfo::getParameters($gatewayName);
		if (is_array($parameters)) {
			$gateway->initialize($parameters);
		}

		return $gateway;
	}

	/**
	 * Generate a return/notify url for off-site gateways (completePayment).
	 * @return string endpoint url
	 */
	protected function getEndpointURL($action, $identifier) {
		return PaymentGatewayController::getEndpointUrl($action, $identifier);
	}

	/**
	 * Record a transaction on this for this payment.
	 * @param string $type the type of transaction to create.
	 *        This is any class that is (or extends) PaymentMessage.
	 * @param array|string|AbstractResponse|AbstractRequest|OmnipayException $data the response to record, or data to store
	 * @return GatewayTransaction newly created dataobject, saved to database.
	 */
	protected function createMessage($type, $data = null) {
		$output = array();
		if (is_string($data)) {
			$output =  array(
				'Message' => $data
			);
		} elseif (is_array($data)) {
			$output = $data;
		} elseif ($data instanceof Omnipay\Common\Exception\OmnipayException) {
			$output = array(
				"Message" => $data->getMessage(),
				"Code" => $data->getCode(),
				"Exception" => get_class($data),
				"Backtrace" => $data->getTraceAsString()
			);
		} elseif ($data instanceof AbstractResponse) {
			$output =  array(
				"Message" => $data->getMessage(),
				"Code" => $data->getCode(),
				"Reference" => $data->getTransactionReference(),
				"Data" => $data->getData()
			);
		} elseif ($data instanceof AbstractRequest) {
			$output = array(
				'Token' => $data->getToken(),
				'CardReference' => $data->getCardReference(),
				'Amount' => $data->getAmount(),
				'Currency' => $data->getCurrency(),
				'Description' => $data->getDescription(),
				'TransactionId' => $data->getTransactionId(),
				'TransactionReference' => $data->getTransactionReference(),
				'ClientIp' => $data->getClientIp(),
				'ReturnUrl' => $data->getReturnUrl(),
				'CancelUrl' => $data->getCancelUrl(),
				'NotifyUrl' => $data->getNotifyUrl(),
				'Parameters' => $data->getParameters()
			);
		}
		$output = array_merge($output, array(
			"PaymentID" => $this->payment->ID,
			"Gateway" => $this->payment->Gateway
		));
		$this->logToFile($output, $type);
		$message = $type::create($output);
		$message->write();
		$this->payment->Messages()->add($message);

		return $message;
	}

	/**
	 * Helper function for logging gateway requests
	 */
	protected function logToFile($data, $type = "") {
		if($logstyle = Payment::config()->file_logging){
			$title = $type." (".$this->payment->Gateway.")";
			if ($logstyle === "verbose") {
				Debug::log(
					$title."\n\n".
					print_r($data, true)
				);
			} elseif($logstyle) {
				Debug::log(implode(", ", array(
					$title,
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

	/**
	 * @return GatewayFactory
	 */
	public function getGatewayFactory() {
        if (!isset($this->gatewayFactory)) {
            $this->gatewayFactory = Injector::inst()->get('Omnipay\Common\GatewayFactory');
        }

		return $this->gatewayFactory;
	}

	/**
	 * @param GatewayFactory $gatewayFactory
	 *
	 * @return $this
	 */
	public function setGatewayFactory($gatewayFactory) {
		$this->gatewayFactory = $gatewayFactory;
		return $this;
	}

	//testing functions (could these instead be injected somehow?)

    /**
     * Set the guzzle client (for testing)
     * @param Guzzle\Http\ClientInterface $httpClient guzzle client for testing
     */
    public static function setHttpClient(Guzzle\Http\ClientInterface $httpClient)
    {
        self::$httpclient = $httpClient;
    }

    public static function getHttpClient()
    {
        return self::$httpclient;
    }

    /**
     * Set the symphony http request (for testing)
     * @param Symfony\Component\HttpFoundation\Request $httpRequest symphony http request for testing
     */
    public static function setHttpRequest(Symfony\Component\HttpFoundation\Request $httpRequest)
    {
        self::$httprequest = $httpRequest;
    }

    public static function getHttpRequest()
    {
        return self::$httprequest;
    }

    // -----------------------------------------------------------------------------------------------------------------
    // Deprecated methods.
    // TODO: Remove with 3.0
    // -----------------------------------------------------------------------------------------------------------------

	/**
	 * Set the guzzle client (for testing)
	 * @param Guzzle\Http\ClientInterface $httpClient guzzle client for testing
     * @deprecated 3.0 Snake-case methods will be deprecated with 3.0, use setHttpClient
	 */
	public static function set_http_client(Guzzle\Http\ClientInterface $httpClient) {
        Deprecation::notice('3.0', 'Snake-case methods will be deprecated with 3.0, use setHttpClient');
		self::setHttpClient($httpClient);
	}

    /**
     * @deprecated 3.0 Snake-case methods will be deprecated with 3.0, use getHttpClient
     */
	public static function get_http_client() {
        Deprecation::notice('3.0', 'Snake-case methods will be deprecated with 3.0, use getHttpClient');
		return self::getHttpClient();
	}

	/**
	 * Set the symphony http request (for testing)
	 * @param Symfony\Component\HttpFoundation\Request $httpRequest symphony http request for testing
     * @deprecated 3.0 Snake-case methods will be deprecated with 3.0, use setHttpRequest
	 */
	public static function set_http_request(Symfony\Component\HttpFoundation\Request $httpRequest) {
        Deprecation::notice('3.0', 'Snake-case methods will be deprecated with 3.0, use setHttpRequest');
        self::setHttpRequest($httpRequest);
	}

    /**
     * @deprecated 3.0 Snake-case methods will be deprecated with 3.0, use getHttpRequest
     */
	public static function get_http_request() {
        Deprecation::notice('3.0', 'Snake-case methods will be deprecated with 3.0, use getHttpRequest');
		return self::getHttpRequest();
	}

}
