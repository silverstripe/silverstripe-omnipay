<?php

namespace SilverStripe\Omnipay\Service;

use SilverStripe\Omnipay\Exception\InvalidConfigurationException;
use SilverStripe\Omnipay\Exception\MissingParameterException;
use Omnipay\Common\Exception\OmnipayException;
use SilverStripe\Omnipay\GatewayInfo;

/**
 * Service used in tandem with AuthorizeService.
 * This service captures a previously authorized amount
 */
class CaptureService extends NotificationCompleteService
{
    //TODO: Ensure that this can also capture partial payments. This would probably have to generate additional Payments

    protected $endState = 'Captured';
    protected $pendingState = 'PendingCapture';
    protected $requestMessageType = 'CaptureRequest';
    protected $errorMessageType = 'CaptureError';

    /**
     * Capture a previously authorized payment
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
        if (!$this->payment->canCapture()) {
            throw new InvalidConfigurationException('Capture of this payment not allowed.');
        }

        if (!$this->payment->isInDB()) {
            $this->payment->write();
        }

        $reference = null;

        // If the gateway isn't manual, we need a transaction reference to refund a payment
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
        if (!$gateway->supportsCapture()) {
            throw new InvalidConfigurationException(
                sprintf('The gateway "%s" doesn\'t support capture', $this->payment->Gateway)
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

        $this->extend('onBeforeCapture', $gatewayData);
        $request = $this->oGateway()->capture($gatewayData);
        $this->extend('onAfterCapture', $request);

        $message = $this->createMessage($this->requestMessageType, $request);
        $message->write();

        try {
            $response = $this->response = $request->send();
        } catch (OmnipayException $e) {
            $this->createMessage($this->errorMessageType, $e);
            return $this->generateServiceResponse(ServiceResponse::SERVICE_ERROR);
        }

        $this->extend('onAfterSendCapture', $request, $response);

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
        $this->createMessage('CapturedResponse', $gatewayMessage);
        $this->payment->extend('onCaptured', $serviceResponse);
    }
}
