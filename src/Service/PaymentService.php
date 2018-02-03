<?php

namespace SilverStripe\Omnipay\Service;

use Guzzle\Http\ClientInterface;
use Omnipay\Common\AbstractGateway;
use Omnipay\Common\CreditCard;
use Omnipay\Common\Exception\OmnipayException;
use Omnipay\Common\GatewayFactory;
use Omnipay\Common\Message\AbstractRequest;
use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\NotificationInterface;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Omnipay\Exception\InvalidConfigurationException;
use SilverStripe\Omnipay\Exception\InvalidStateException;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\Helper;
use SilverStripe\Omnipay\Model\Message\GatewayErrorMessage;
use SilverStripe\Omnipay\Model\Message\NotificationError;
use SilverStripe\Omnipay\Model\Message\NotificationPending;
use SilverStripe\Omnipay\Model\Message\NotificationSuccessful;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\PaymentGatewayController;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides wrapper methods for interacting with the omnipay gateways library.
 *
 * Interfaces with the omnipay library.
 */
abstract class PaymentService
{
    use Extensible;
    use Injectable;

    /**
     *
     */
    private static $dependencies = [
        'logger' => '%$SilverStripe\Omnipay\Logger',
    ];

    /**
     * @var \Guzzle\Http\ClientInterface
     */
    private static $httpClient;

    /**
     * @var \Symfony\Component\HttpFoundation\Request
     */
    private static $httpRequest;

    /**
     * @var Payment
     */
    protected $payment;

    /**
     * @var AbstractResponse
     */
    protected $response;

    /**
     * @var GatewayFactory
     */
    protected $gatewayFactory;

    /**
     * @param Payment
     */
    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }

    /**
     * Initiate a gateway request with some user/application supplied data.
     * @param array $data payment data
     * @throws InvalidStateException when the payment is in a state that prevents running `complete`
     * @throws InvalidConfigurationException when there's a misconfiguration in the module itself
     * @return ServiceResponse the service response
     */
    abstract public function initiate($data = array());

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
    abstract public function complete($data = array(), $isNotification = false);

    /**
     * Cancel a payment
     *
     * @return ServiceResponse
     */
    public function cancel()
    {
        if (!$this->payment->IsComplete()) {
            $this->payment->Status = 'Void';
            $this->payment->write();
            Helper::safeExtend($this->payment, 'onCancelled');
        }

        return $this->generateServiceResponse(ServiceResponse::SERVICE_CANCELLED);
    }

    /**
     * Get the payment associated with this service.
     *
     * @return Payment
     */
    public function getPayment()
    {
        return $this->payment;
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
            $this->createMessage(NotificationError::class, $e);
            return $this->generateServiceResponse(
                ServiceResponse::SERVICE_NOTIFICATION | ServiceResponse::SERVICE_ERROR
            );
        }

        if (!($notification instanceof NotificationInterface)) {
            $this->createMessage(
                NotificationError::class,
                'Notification from Omnipay doesn\'t implement NotificationInterface'
            );
            return $this->generateServiceResponse(
                ServiceResponse::SERVICE_NOTIFICATION | ServiceResponse::SERVICE_ERROR
            );
        }

        switch ($notification->getTransactionStatus()) {
            case NotificationInterface::STATUS_COMPLETED:
                $this->createMessage(NotificationSuccessful::class, $notification);
                return $this->generateServiceResponse(ServiceResponse::SERVICE_NOTIFICATION, $notification);
                break;
            case NotificationInterface::STATUS_PENDING:
                $this->createMessage(NotificationPending::class, $notification);
                return $this->generateServiceResponse(
                    ServiceResponse::SERVICE_NOTIFICATION | ServiceResponse::SERVICE_PENDING,
                    $notification
                );
        }

        // The only status left is error
        $this->createMessage(NotificationError::class, $notification);
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
            $data['clientIp'] = Controller::curr()->getRequest()->getIP();
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
        return PaymentGatewayController::getEndpointUrl($action, $this->payment->Identifier, $this->payment->Gateway);
    }

    /**
     * Get a service response from the given Omnipay response
     * @param AbstractResponse $omnipayResponse
     * @param bool $isNotification whether or not this response is a response to a notification
     * @return ServiceResponse
     */
    protected function wrapOmnipayResponse(AbstractResponse $omnipayResponse, $isNotification = false)
    {
        if ($isNotification) {
            $flags = ServiceResponse::SERVICE_NOTIFICATION;
            if (!$omnipayResponse->isSuccessful()) {
                $flags |= ServiceResponse::SERVICE_ERROR;
            }
            return $this->generateServiceResponse($flags, $omnipayResponse);
        }

        $isAsync = GatewayInfo::shouldUseAsyncNotifications($this->payment->Gateway);
        $flags = $isAsync ? ServiceResponse::SERVICE_PENDING : 0;

        if (!$omnipayResponse->isSuccessful() && !$omnipayResponse->isRedirect() && !$isAsync) {
            $flags |= ServiceResponse::SERVICE_ERROR;
        }

        return $this->generateServiceResponse($flags, $omnipayResponse);
    }

    /**
     * Mark this payment process as completed.
     * This sets the desired end-status on the payment, sets the transaction reference and writes the payment.
     *
     * In subclasses, you'll want to override this and:
     * * Log/Write the GatewayMessage
     * * Call a "complete" hook
     *
     * Don't forget to call the parent method from your subclass!
     *
     * @param string $endStatus the end state to set on the payment
     * @param ServiceResponse $serviceResponse the service response
     * @param mixed $gatewayMessage the message from Omnipay
     * @return void
     */
    protected function markCompleted($endStatus, ServiceResponse $serviceResponse, $gatewayMessage)
    {
        $this->payment->Status = $endStatus;
        if ($gatewayMessage && ($reference = $gatewayMessage->getTransactionReference())) {
            $this->payment->TransactionReference = $reference;
        }
        $this->payment->write();
    }

    /**
     * Create a partial payment that will be based on the current payment.
     * This new payment will inherit the Gateway, TransactionReference, SuccessUrl and FailureUrl
     * of the initial payment.
     * @param float $amount the amount that the partial payment should have
     * @param string $status the desired payment status
     * @param boolean $write whether or not to directly write the new Payment to DB (optional)
     * @return Payment the newly created payment (already written to the DB)
     */
    protected function createPartialPayment($amount, $status, $write = true)
    {
        /** @var \Payment $payment */
        $payment = Payment::create(array(
            'Gateway' => $this->payment->Gateway,
            'TransactionReference' => $this->payment->TransactionReference,
            'SuccessUrl' => $this->payment->SuccessUrl,
            'FailureUrl' => $this->payment->FailureUrl,
            'InitialPaymentID' => $this->payment->ID
        ));

        $payment->setCurrency($this->payment->getCurrency());
        $payment->setAmount($amount);

        // set status later, because otherwise amount and currency become immutable
        $payment->Status = $status;

        // allow extensions to update/modify the partial payment
        Helper::safeExtend($this, 'updatePartialPayment', $payment, $this->payment);

        if ($write) {
            Helper::safeguard(function () use (&$payment) {
                $payment->write();
            }, 'Unable to write newly created partial Payment!');
        }

        return $payment;
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

        if ($omnipayData) {
            $response->setOmnipayResponse($omnipayData);
        }

        // redirects and notifications don't need a target URL.
        if (!$response->isNotification() && !$response->isRedirect()) {
            $response->setTargetUrl(
                ($response->isError() || $response->isCancelled())
                    ? $this->payment->FailureUrl
                    : $this->payment->SuccessUrl
            );
        }

        // Hook to update service response via extensions. This can be used to customize the service response
        Helper::safeExtend($this, 'updateServiceResponse', $response);

        return $response;
    }

    /**
     * Record a transaction on this for this payment.
     *
     * @param string $type the type of transaction to create.
     *        This is any class that is (or extends) PaymentMessage.
     *
     * @param array|string|AbstractResponse|AbstractRequest|OmnipayException|NotificationInterface $data
     *
     * @return PaymentMessage newly created DataObject, saved to database.
     */
    protected function createMessage($type, $data = null)
    {
        $output = array();

        if (is_string($data)) {
            $output = [
                'Message' => $data
            ];
        } elseif (is_array($data)) {
            $output = $data;
        } elseif ($data instanceof OmnipayException) {
            $output = [
                'Message' => $data->getMessage(),
                'Code' => $data->getCode(),
                'Exception' => get_class($data),
                'Backtrace' => $data->getTraceAsString()
            ];
        } elseif ($data instanceof AbstractResponse) {
            $output = [
                'Message' => $data->getMessage(),
                'Code' => $data->getCode(),
                'Reference' => $data->getTransactionReference(),
                'Data' => $data->getData()
            ];
        } elseif ($data instanceof AbstractRequest) {
            $output = [
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
            ];
        } elseif ($data instanceof NotificationInterface) {
            $output = [
                'Message' => $data->getMessage(),
                'Code' => $data->getTransactionStatus(),
                'Reference' => $data->getTransactionReference(),
                'Data' => $data->getData()
            ];
        }
        $output = array_merge($output, [
            'PaymentID' => $this->payment->ID,
            'Gateway' => $this->payment->Gateway
        ]);

        $this->logToFile($output, $type);

        $message = Injector::inst()->create($type)->update($output);
        $message->write();

        $this->payment->Messages()->add($message);

        return $message;
    }

    /**
     * Helper function for logging gateway requests
     */
    protected function logToFile($data, $type = '')
    {
        $this->logger->log(
            // Log as error if we get a GatewayErrorMessage
            is_subclass_of($type, GatewayErrorMessage::class) ? 'error' : 'info',
            // Log title
            sprintf('%s (%s)', $type, $this->payment->Gateway),
            // Log context (just output the data)
            Helper::prepareForLogging($data)
        );
    }

    /**
     * @return GatewayFactory
     */
    public function getGatewayFactory()
    {
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

    /**
     * Set the guzzle client
     *
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
     * Set the symphony http request
     *
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
}
