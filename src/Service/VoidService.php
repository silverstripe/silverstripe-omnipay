<?php

namespace SilverStripe\Omnipay\Service;

use SilverStripe\Omnipay\Exception\InvalidConfigurationException;
use SilverStripe\Omnipay\Exception\MissingParameterException;
use Omnipay\Common\Exception\OmnipayException;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\Helper\ErrorHandling;
use SilverStripe\Omnipay\Model\Message;

class VoidService extends NotificationCompleteService
{
    protected $startState = 'Authorized';

    protected $endState = 'Void';

    protected $pendingState = 'PendingVoid';

    protected $requestMessageType = Message\VoidRequest::class;

    protected $errorMessageType = Message\VoidError::class;

    /**
     * Void/cancel a payment
     *
     * If the transaction-reference of the payment to capture is known, pass it via $data as
     * `transactionReference` parameter. Otherwise the service will try to look up the reference
     * from previous payment messages.
     *
     * If there's no transaction-reference to be found, this method will raise an exception.
     *
     * @inheritdoc
     * @throws MissingParameterException if no transaction reference can be found from messages or parameters
     */
    public function initiate($data = array())
    {
        if (!$this->payment->canVoid()) {
            throw new InvalidConfigurationException('Voiding of this payment not allowed.');
        }

        if (!$this->payment->isInDB()) {
            $this->payment->write();
        }

        $reference = null;

        // If the gateway isn't manual, we need a transaction reference to void a payment
        if (!GatewayInfo::isManual($this->payment->Gateway)) {
            if (!empty($data['transactionReference'])) {
                $reference = $data['transactionReference'];
            } elseif (!empty($data['receipt'])) { // legacy code?
                $reference = $data['receipt'];
            } else {
                $reference = $this->payment->TransactionReference;
            }

            if (empty($reference)) {
                throw new MissingParameterException('transactionReference not found and is not set as parameter');
            }
        }

        $gateway = $this->oGateway();

        if (!$gateway->supportsVoid()) {
            throw new InvalidConfigurationException(
                sprintf('The gateway "%s" doesn\'t support void', $this->payment->Gateway)
            );
        }

        $gatewayData = array_merge(
            $data,
            array(
                'amount' => (float)$this->payment->MoneyAmount,
                'currency' => $this->payment->MoneyCurrency,
                'transactionReference' => $reference,
                'notifyUrl' => $this->getEndpointUrl('notify')
            )
        );

        $this->extend('onBeforeVoid', $gatewayData);
        $request = $this->oGateway()->void($gatewayData);
        $this->extend('onAfterVoid', $request);

        $message = $this->createMessage($this->requestMessageType, $request);
        $message->write();

        try {
            $response = $this->response = $request->send();
        } catch (OmnipayException $e) {
            $this->createMessage($this->errorMessageType, $e);

            return $this->generateServiceResponse(ServiceResponse::SERVICE_ERROR);
        }

        ErrorHandling::safeExtend($this, 'onAfterSendVoid', $request, $response);

        $serviceResponse = $this->wrapOmnipayResponse($response);

        if ($serviceResponse->isAwaitingNotification()) {
            $this->payment->Status = $this->pendingState;
            $this->payment->write();
        } else {
            if ($serviceResponse->isError()) {
                $this->createMessage($this->errorMessageType, $response);
            } else {
                $this->markCompleted($this->endState, $serviceResponse, $response);
            }
        }

        return $serviceResponse;
    }

    protected function markCompleted($endStatus, ServiceResponse $serviceResponse, $gatewayMessage)
    {
        parent::markCompleted($endStatus, $serviceResponse, $gatewayMessage);
        $this->createMessage(Message\VoidedResponse::class, $gatewayMessage);

        ErrorHandling::safeExtend($this->payment, 'onVoid', $serviceResponse);
    }
}
