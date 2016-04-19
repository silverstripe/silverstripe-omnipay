<?php

namespace SilverStripe\Omnipay\Service;


use Omnipay\Common\Message\NotificationInterface;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\PaymentGatewayController;
use SilverStripe\Omnipay\Exception\InvalidConfigurationException;
use SilverStripe\Omnipay\Exception\InvalidStateException;
use Guzzle\Http\ClientInterface;
use Omnipay\Common\AbstractGateway;
use Omnipay\Common\GatewayFactory;
use Omnipay\Common\CreditCard;
use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\AbstractRequest;
use Omnipay\Common\Exception\OmnipayException;
use Symfony\Component\EventDispatcher\Tests\Service;
use Symfony\Component\HttpFoundation\Request;

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
abstract class PaymentService extends \Object
{
    /**
     * @var \Guzzle\Http\ClientInterface
     */
    private static $httpClient;

    /**
     * @var \Symfony\Component\HttpFoundation\Request
     */
    private static $httpRequest;

    /**
     * @var \Payment
     */
    protected $payment;

    /**
     * @var String
     */
    protected $returnUrl;

    /**
     * @var String
     */
    protected $cancelUrl;

    /**
     * @var AbstractResponse
     */
    protected $response;

    /**
     * @var GatewayFactory
     */
    protected $gatewayFactory;

    /**
     * @param \Payment
     */
    public function __construct(\Payment $payment)
    {
        parent::__construct();
        $this->payment = $payment;
    }

    /**
     * Get the url to return to, that has been previously stored.
     * This is not a database field.
     * @return string the url
     */
    public function getReturnUrl()
    {
        return $this->returnUrl;
    }

    /**
     * Set the url to redirect to after payment is made/attempted.
     * This function also populates the cancel url, if it is empty.
     * @return $this this object for chaining
     */
    public function setReturnUrl($url)
    {
        $this->returnUrl = $url;
        if (!$this->cancelUrl) {
            $this->cancelUrl = $url;
        }

        return $this;
    }

    /**
     * @return string cancel url
     */
    public function getCancelUrl()
    {
        return $this->cancelUrl;
    }

    /**
     * Set the url to redirect to after payment is cancelled
     * @return $this this object for chaining
     */
    public function setCancelUrl($url)
    {
        $this->cancelUrl = $url;

        return $this;
    }

    /**
     * Initiate a gateway request with some user/application supplied data.
     * @param array $data payment data
     * @throws InvalidStateException when the payment is in a state that prevents running `complete`
     * @throws InvalidConfigurationException when there's a misconfiguration in the module itself
     * @return ServiceResponse the service response
     */
    abstract function initiate($data = array());

    /**
     * Complete a previously initiated gateway request.
     * This is separate from initiate, since some requests require more than one step. Eg. offsite payments or
     * payments to gateways that return asynchronous responses.
     * @param array $data payment data
     * @param bool $isNotification whether or not this was called from a notification callback (async). Defaults to false
     * @throws InvalidStateException when the payment is in a state that prevents running `complete`
     * @throws InvalidConfigurationException when there's a misconfiguration in the module itself
     * @return ServiceResponse the service response
     */
    abstract function complete($data = array(), $isNotification = false);

    /**
     * Cancel a payment
     * @return ServiceResponse
     */
    public function cancel()
    {
        if (!$this->payment->IsComplete()) {
            $this->payment->Status = 'Void';
            $this->payment->write();
            $this->payment->extend('onCancelled');
        }

        return $this->generateServiceResponse(ServiceResponse::SERVICE_CANCELLED);
    }

    /**
     * Get the omnipay gateway associated with this payment,
     * with configuration applied.
     *
     * @throws \RuntimeException - when gateway doesn't exist.
     * @return AbstractGateway omnipay gateway class
     */
    public function oGateway()
    {
        $gatewayName = $this->payment->Gateway;
        $gateway = $this->getGatewayFactory()->create(
            $gatewayName,
            self::$httpClient,
            self::$httpRequest
        );

        $parameters = GatewayInfo::getParameters($gatewayName);
        if (is_array($parameters)) {
            $gateway->initialize($parameters);
        }

        return $gateway;
    }

    /**
     * Handle a notification via gateway->acceptNotification.
     *
     * This just invokes `acceptNotification` on the gateway (if available) and wraps the return value in
     * the proper ServiceResponse.
     *
     * @return ServiceResponse
     * @throws InvalidConfigurationException
     */
    public function handleNotification()
    {
        $gateway = $this->oGateway();
        if (!$gateway->supportsAcceptNotification()) {
            throw new InvalidConfigurationException(
                sprintf('The gateway "%s" doesn\'t support "acceptNotification"', $this->payment->Gateway)
            );
        }

        // Deal with the notification, according to the omnipay documentation
        // https://github.com/thephpleague/omnipay#incoming-notifications
        $notification = null;
        try {
            $notification = $gateway->acceptNotification();
        } catch (\Omnipay\Common\Exception\OmnipayException $e) {
            $this->createMessage('NotificationError', $e);
            return $this->generateServiceResponse(
                ServiceResponse::SERVICE_NOTIFICATION | ServiceResponse::SERVICE_ERROR
            );
        }

        if (!($notification instanceof NotificationInterface)) {
            $this->createMessage(
                'NotificationError',
                'Notification from Omnipay doesn\'t implement NotificationInterface'
            );
            return $this->generateServiceResponse(
                ServiceResponse::SERVICE_NOTIFICATION | ServiceResponse::SERVICE_ERROR
            );
        }

        switch ($notification->getTransactionStatus()) {
            case NotificationInterface::STATUS_COMPLETED:
                $this->createMessage('NotificationSuccessful', $notification);
                return $this->generateServiceResponse(ServiceResponse::SERVICE_NOTIFICATION, $notification);
                break;
            case NotificationInterface::STATUS_PENDING:
                $this->createMessage('NotificationPending', $notification);
                return $this->generateServiceResponse(
                    ServiceResponse::SERVICE_NOTIFICATION | ServiceResponse::SERVICE_PENDING,
                    $notification
                );
        }

        // The only status left is error
        $this->createMessage('NotificationError', $notification);
        return $this->generateServiceResponse(
            ServiceResponse::SERVICE_NOTIFICATION | ServiceResponse::SERVICE_ERROR,
            $notification
        );
    }

    /**
     * Collect common data parameters to pass to the gateway.
     * This method should merge in common data that is required by all services.
     *
     * If you override this method, make sure to merge your data with parent::gatherGatewayData
     *
     * @param array $data incoming data for the gateway
     * @param boolean $includeCardOrToken whether or not to include card or token data
     * @return array
     */
    protected function gatherGatewayData($data = array(), $includeCardOrToken = true)
    {
        //set the client IP address, if not already set
        if (!isset($data['clientIp'])) {
            $data['clientIp'] = \Controller::curr()->getRequest()->getIP();
        }

        $gatewaydata = array_merge($data, array(
            'amount' => (float)$this->payment->MoneyAmount,
            'currency' => $this->payment->MoneyCurrency,
            //set all gateway return/cancel/notify urls to PaymentGatewayController endpoint
            'returnUrl' => $this->getEndpointUrl("complete"),
            'cancelUrl' => $this->getEndpointUrl("cancel"),
            'notifyUrl' => $this->getEndpointUrl("notify")
        ));

        // Often, the shop will want to pass in a transaction ID (order #, etc), but if there's
        // not one we need to set it as Ominpay requires this.
        if (!isset($gatewaydata['transactionId'])) {
            $gatewaydata['transactionId'] = $this->payment->Identifier;
        }

        if ($includeCardOrToken) {
            // We only look for a card if we aren't already provided with a token
            // Increasingly we can expect tokens or nonce's to be more common (e.g. Stripe and Braintree)
            $tokenKey = GatewayInfo::getTokenKey($this->payment->Gateway);
            if (empty($gatewaydata[$tokenKey])) {
                $gatewaydata['card'] = $this->getCreditCard($data);
            } elseif ($tokenKey !== 'token') {
                // some gateways (eg. braintree) use a different key but we need
                // to normalize that for omnipay
                $gatewaydata['token'] = $gatewaydata[$tokenKey];
                unset($gatewaydata[$tokenKey]);
            }
        }

        return $gatewaydata;
    }

    /**
     * Generate a return/notify url for off-site gateways (completePayment).
     * @param string $action the action to call on the endpoint (complete, notify or cancel)
     * @return string endpoint url
     */
    protected function getEndpointUrl($action)
    {
        return PaymentGatewayController::getEndpointUrl($action, $this->payment->Identifier);
    }

    /**
     * Get a service response from the given Omnipay response
     * @param AbstractResponse $omnipayResponse
     * @param bool $isNotification whether or not this response is a response to a notification
     * @return ServiceResponse
     */
    protected function wrapOmnipayResponse(AbstractResponse $omnipayResponse, $isNotification = false)
    {
        if($isNotification){
            $flags = ServiceResponse::SERVICE_NOTIFICATION;
            if (!$omnipayResponse->isSuccessful()) {
                $flags |= ServiceResponse::SERVICE_ERROR;
            }
            return $this->generateServiceResponse($flags, $omnipayResponse);
        }

        $isAsync = GatewayInfo::shouldUseAsyncNotifications($this->payment->Gateway);
        $flags = $isAsync ? ServiceResponse::SERVICE_PENDING : 0;

        if(!$omnipayResponse->isSuccessful() && !$omnipayResponse->isRedirect() && !$isAsync){
            $flags |= ServiceResponse::SERVICE_ERROR;
        }

        return $this->generateServiceResponse($flags, $omnipayResponse);
    }

    /**
     * Generate a service response
     * @param int $flags a combination of service flags
     * @param AbstractResponse|NotificationInterface|null $omnipayData the response or notification from the Omnipay gateway
     * @return ServiceResponse
     */
    protected function generateServiceResponse(
        $flags,
        $omnipayData = null
    ) {
        $response = new ServiceResponse($this->payment, $flags);

        if($omnipayData){
            $response->setOmnipayResponse($omnipayData);
        }

        // redirects and notifications don't need a target URL.
        if(!$response->isNotification() && !$response->isRedirect()){
            $response->setTargetUrl(
                ($response->isError() || $response->isCancelled())
                    ? $this->getCancelUrl()
                    : $this->getReturnUrl()
            );
        }

        // Hook to update service response via extensions. This can be used to customize the service response
        $this->extend('updateServiceResponse', $response);

        return $response;
    }

    /**
     * Record a transaction on this for this payment.
     * @param string $type the type of transaction to create.
     *        This is any class that is (or extends) PaymentMessage.
     * @param array|string|AbstractResponse|AbstractRequest|OmnipayException|NotificationInterface $data the response to record, or data to store
     * @return \PaymentMessage newly created DataObject, saved to database.
     */
    protected function createMessage($type, $data = null)
    {
        $output = array();
        if (is_string($data)) {
            $output = array(
                'Message' => $data
            );
        } elseif (is_array($data)) {
            $output = $data;
        } elseif ($data instanceof OmnipayException) {
            $output = array(
                'Message' => $data->getMessage(),
                'Code' => $data->getCode(),
                'Exception' => get_class($data),
                'Backtrace' => $data->getTraceAsString()
            );
        } elseif ($data instanceof AbstractResponse) {
            $output = array(
                'Message' => $data->getMessage(),
                'Code' => $data->getCode(),
                'Reference' => $data->getTransactionReference(),
                'Data' => $data->getData()
            );
        } elseif ($data instanceof AbstractRequest) {
            $output = array(
                'Token' => $data->getToken(),
                'CardReference' => $data->getCardReference(),
                'Amount' => $data->getAmount(),
                'Currency' => $data->getCurrency(),
                'Description' => $data->getDescription(),
                'TransactionId' => $data->getTransactionId(),
                'Reference' => $data->getTransactionReference(),
                'ClientIp' => $data->getClientIp(),
                'ReturnUrl' => $data->getReturnUrl(),
                'CancelUrl' => $data->getCancelUrl(),
                'NotifyUrl' => $data->getNotifyUrl(),
                'Parameters' => $data->getParameters()
            );
        } elseif ($data instanceof NotificationInterface) {
            $output = array(
                'Message' => $data->getMessage(),
                'Code' => $data->getTransactionStatus(),
                'Reference' => $data->getTransactionReference(),
                'Data' => $data->getData()
            );
        }
        $output = array_merge($output, array(
            'PaymentID' => $this->payment->ID,
            'Gateway' => $this->payment->Gateway
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
    protected function logToFile($data, $type = "")
    {
        if ($logstyle = \Payment::config()->file_logging) {
            $title = $type . " (" . $this->payment->Gateway . ")";
            if ($logstyle === "verbose") {
                \Debug::log(
                    $title . "\n\n" .
                    print_r($data, true)
                );
            } elseif ($logstyle) {
                \Debug::log(implode(", ", array(
                    $title,
                    isset($data['Message']) ? $data['Message'] : " ",
                    isset($data['Code']) ? $data['Code'] : " ",
                )));
            }
        }
    }

    /**
     * @return GatewayFactory
     */
    public function getGatewayFactory()
    {
        if (!isset($this->gatewayFactory)) {
            $this->gatewayFactory = \Injector::inst()->get('Omnipay\Common\GatewayFactory');
        }

        return $this->gatewayFactory;
    }

    /**
     * @param GatewayFactory $gatewayFactory
     *
     * @return $this
     */
    public function setGatewayFactory($gatewayFactory)
    {
        $this->gatewayFactory = $gatewayFactory;
        return $this;
    }

    /**
     * @return \Omnipay\Common\CreditCard
     */
    protected function getCreditCard($data)
    {
        return new CreditCard($data);
    }

    //testing functions (could these instead be injected somehow?)

    /**
     * Set the guzzle client (for testing)
     * @param \Guzzle\Http\ClientInterface $httpClient guzzle client for testing
     */
    public static function setHttpClient(ClientInterface $httpClient)
    {
        self::$httpClient = $httpClient;
    }

    public static function getHttpClient()
    {
        return self::$httpClient;
    }

    /**
     * Set the symphony http request (for testing)
     * @param \Symfony\Component\HttpFoundation\Request $httpRequest symphony http request for testing
     */
    public static function setHttpRequest(Request $httpRequest)
    {
        self::$httpRequest = $httpRequest;
    }

    public static function getHttpRequest()
    {
        return self::$httpRequest;
    }

    // -----------------------------------------------------------------------------------------------------------------
    // Deprecated methods.
    // TODO: Remove with 3.0
    // -----------------------------------------------------------------------------------------------------------------

    /**
     * Set the guzzle client (for testing)
     * @param \Guzzle\Http\ClientInterface $httpClient guzzle client for testing
     * @deprecated 3.0 Snake-case methods will be deprecated with 3.0, use setHttpClient
     */
    public static function set_http_client(ClientInterface $httpClient)
    {
        \Deprecation::notice('3.0', 'Snake-case methods will be deprecated with 3.0, use setHttpClient');
        self::setHttpClient($httpClient);
    }

    /**
     * @deprecated 3.0 Snake-case methods will be deprecated with 3.0, use getHttpClient
     */
    public static function get_http_client()
    {
        \Deprecation::notice('3.0', 'Snake-case methods will be deprecated with 3.0, use getHttpClient');
        return self::getHttpClient();
    }

    /**
     * Set the symphony http request (for testing)
     * @param \Symfony\Component\HttpFoundation\Request $httpRequest symphony http request for testing
     * @deprecated 3.0 Snake-case methods will be deprecated with 3.0, use setHttpRequest
     */
    public static function set_http_request(Request $httpRequest)
    {
        \Deprecation::notice('3.0', 'Snake-case methods will be deprecated with 3.0, use setHttpRequest');
        self::setHttpRequest($httpRequest);
    }

    /**
     * @deprecated 3.0 Snake-case methods will be deprecated with 3.0, use getHttpRequest
     */
    public static function get_http_request()
    {
        \Deprecation::notice('3.0', 'Snake-case methods will be deprecated with 3.0, use getHttpRequest');
        return self::getHttpRequest();
    }

}
