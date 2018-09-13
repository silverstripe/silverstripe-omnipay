<?php

namespace SilverStripe\Omnipay\Service;

use SilverStripe\Omnipay\Exception\InvalidStateException;
use SilverStripe\Omnipay\Exception\InvalidConfigurationException;
use SilverStripe\Omnipay\Model\Payment;

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
    /** @var string the start state  */
    protected $startState;

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

        // we're still in the start state, cannot complete here
        if ($this->payment->Status === $this->startState) {
            return $this->generateServiceResponse(ServiceResponse::SERVICE_NOTIFICATION | ServiceResponse::SERVICE_ERROR);
        }

        if ($this->payment->Status !== $this->pendingState) {
            throw new InvalidStateException('Cannot modify this payment. Status is not "'. $this->pendingState .'"');
        }

        $serviceResponse = $this->handleNotification();

        // exit early
        if ($serviceResponse->isError()) {
            $this->notificationFailure($serviceResponse);
            return $serviceResponse;
        }

        // safety check the payment transaction-number against the transaction reference we get from the notification
        if (!(
            $serviceResponse->getOmnipayResponse() &&
            $serviceResponse->getOmnipayResponse()->getTransactionReference() == $this->payment->TransactionReference
        )) {
            // flag as an error if transaction references don't match
            $serviceResponse->addFlag(ServiceResponse::SERVICE_ERROR);
            $this->createMessage($this->errorMessageType, 'Transaction references do not match!');
        }

        // check if we're done
        if (!$serviceResponse->isError() && !$serviceResponse->isAwaitingNotification()) {
            $this->markCompleted($this->endState, $serviceResponse, $serviceResponse->getOmnipayResponse());
        }

        return $serviceResponse;
    }

    /**
     * Method to handle notification failures. Here we have to check if the gateway actually reported a failure
     * and then update the payment status accordingly!
     * @param ServiceResponse $serviceResponse
     * @return void
     */
    protected function notificationFailure($serviceResponse)
    {
        $omnipayResponse = $serviceResponse->getOmnipayResponse();

        // if there's no response from the gateway, don't bother. Errors are already in messages/log
        if (!$omnipayResponse) {
            return;
        }

        // void any pending partial payments
        $pending = $this->payment->getPartialPayments()->filter('Status', $this->pendingState);

        /** @var Payment $payment */
        foreach ($pending as $payment) {
            $payment->Status = 'Void';
            $payment->write();
        }

        // reset the payment to the start-state
        $this->payment->Status = $this->startState;
        $this->payment->write();
    }
}
