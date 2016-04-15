<?php

namespace SilverStripe\Omnipay\Service;

use SilverStripe\Omnipay\Exception\InvalidStateException;
use SilverStripe\Omnipay\Exception\InvalidConfigurationException;


/**
 * Abstract base class for payment services that operate on an existing transaction. Examples of this are:
 * Void, Refund and Capture.
 *
 * This service models the following pattern:
 * * A request is made to the gateway, using an existing transaction ID.
 * * If the request is successful, the goal state is reached.
 * * If the payment provider will report success via async notification, the notification is being handled in the `complete` method.
 *
 * @package SilverStripe\Omnipay\Service
 */
abstract class NotificationCompleteService extends PaymentService
{

    /** @var string the end state to reach */
    protected $endState;

    /** @var string the pending state name */
    protected $pendingState;

    /** @var string message type used to store requests */
    protected $requestMessageType;

    /** @var  string message type used to store errors */
    protected $errorMessageType;


    /**
     * Complete a pending task.
     * This is only needed for notification, so this method will always assume $isNotification is true!
     *
     * @param array $data
     * @param bool $isNotification
     * @return ServiceResponse
     * @throws InvalidConfigurationException
     * @throws InvalidStateException
     */
    public function complete($data = array(), $isNotification = true)
    {
        // The payment is already in the desired endstate
        if ($this->payment->Status === $this->endState) {
            return $this->generateServiceResponse(ServiceResponse::SERVICE_NOTIFICATION);
        }

        if ($this->payment->Status !== $this->pendingState) {
            throw new InvalidStateException('Cannot modify this payment. Status is not "'. $this->pendingState .'"');
        }

        $serviceResponse = $this->handleNotification();

        // exit early
        if($serviceResponse->isError()){
            return $serviceResponse;
        }

        // Find the matching request message
        $msg = $this->payment->getLatestMessageOfType($this->requestMessageType);

        // safety check the payment number against the transaction reference we get from the notification
        if (!(
            $msg &&
            $serviceResponse->getOmnipayResponse() &&
            $serviceResponse->getOmnipayResponse()->getTransactionReference() == $msg->Reference
        )) {
            // flag as an error if transaction references don't match or aren't available
            $serviceResponse->addFlag(ServiceResponse::SERVICE_ERROR);
            $this->createMessage(
                $this->errorMessageType,
                $msg  ? 'No transaction reference found for this Payment!' : 'Transaction references do not match!'
            );
        }

        // check if we're done
        if (!$serviceResponse->isError() && !$serviceResponse->isAwaitingNotification()) {
            $this->markCompleted($serviceResponse, $serviceResponse->getOmnipayResponse());
        }

        return $serviceResponse;
    }

    /**
     * Mark this payment process as completed.
     * Here you'll usually do the following:
     * * Set the proper status on Payment and write the payment.
     * * Log/Write the GatewayMessage
     * * Call a "complete" hook
     *
     * @param ServiceResponse $serviceResponse the service response
     * @param mixed $gatewayMessage the message from Omnipay
     * @return void
     */
    protected abstract function markCompleted(ServiceResponse $serviceResponse, $gatewayMessage);
}
