<?php

namespace SilverStripe\Omnipay\Service;

use SilverStripe\Omnipay\Exception\InvalidStateException;
use SilverStripe\Omnipay\Exception\InvalidConfigurationException;
use SilverStripe\Omnipay\Helper;

class CreateCardService extends PaymentService
{

    /**
     * Start a createcard request
     *
     * @inheritdoc
     */
    public function initiate($data = array())
    {
        if ($this->payment->Status !== 'Created') {
            throw new InvalidStateException('Cannot create a card for this payment. Status is not "Created"');
        }

        if (!$this->payment->isInDB()) {
            $this->payment->write();
        }

        $gateway = $this->oGateway();
        if (!$gateway->supportsCreateCard()) {
            throw new InvalidConfigurationException(
            sprintf('The gateway "%s" doesn\'t support create card', $this->payment->Gateway)
            );
        }

        $gatewayData = $this->gatherGatewayData($data);

        $this->extend('onBeforeCreateCard', $gatewayData);
        $request = $this->oGateway()->createCard($gatewayData);
        $this->extend('onAfterCreateCard', $request);

        $this->createMessage('CreateCardRequest', $request);

        try {
            $response       = $this->response = $request->send();
        } catch (\Omnipay\Common\Exception\OmnipayException $e) {
            $this->createMessage('CreateCardError', $e);
            // create an error response
            return $this->generateServiceResponse(ServiceResponse::SERVICE_ERROR);
        }

        Helper::safeExtend($this, 'onAfterSendCreateCard', $request, $response);

        $serviceResponse = $this->wrapOmnipayResponse($response);

        if ($serviceResponse->isRedirect() || $serviceResponse->isAwaitingNotification()) {
            $this->payment->Status = 'PendingCreateCard';
            $this->payment->write();

            $this->createMessage(
                $serviceResponse->isRedirect() ? 'CreateCardRedirectResponse' : 'AwaitingCreateCardResponse', $response
            );
        } elseif ($serviceResponse->isError()) {
            $this->createMessage('CreateCardError', $response);
        } else {
            $this->markCompleted('CardCreated', $serviceResponse, $response);
        }

        return $serviceResponse;
    }

    /**
     * Finalise this createcard request, after off-site external processing.
     * This is usually only called by PaymentGatewayController.
     * @inheritdoc
     */
    public function complete($data = array(), $isNotification = false)
    {
        $flags = $isNotification ? ServiceResponse::SERVICE_NOTIFICATION : 0;

        // The card is already created
        if ($this->payment->Status === 'CardCreated') {
            return $this->generateServiceResponse($flags);
        }

        if ($this->payment->Status !== 'PendingCreateCard') {
            throw new InvalidStateException('Cannot complete this payment. Status is not "PendingCreateCard"');
        }

        $gateway = $this->oGateway();
        if (!method_exists($gateway, "completeCreateCard")) {
            throw new InvalidConfigurationException(
            sprintf('The gateway "%s" doesn\'t support completeCreateCard', $this->payment->Gateway)
            );
        }

        // purchase and completePurchase should use the same data
        $gatewayData = $this->gatherGatewayData($data);

        $this->extend('onBeforeCompleteCreateCard', $gatewayData);
        $request = $gateway->completeCreateCard($gatewayData);
        $this->extend('onAfterCompleteCreateCard', $request);

        $this->createMessage('CompleteCreateCardRequest', $request);
        $response = null;
        try {
            $response       = $this->response = $request->send();
        } catch (\Omnipay\Common\Exception\OmnipayException $e) {
            $this->createMessage('CompleteCreateCardError', $e);
            return $this->generateServiceResponse($flags | ServiceResponse::SERVICE_ERROR);
        }

        $serviceResponse = $this->wrapOmnipayResponse($response, $isNotification);

        if ($serviceResponse->isError()) {
            $this->createMessage('CompleteCreateCardError', $response);
            return $serviceResponse;
        }

        if (!$serviceResponse->isAwaitingNotification()) {
            $this->markCompleted('CardCreated', $serviceResponse, $response);
        } else {
            Helper::safeExtend($this->payment, 'onAwaitingCreateCard', $serviceResponse);
        }

        return $serviceResponse;
    }

    protected function markCompleted($endStatus, ServiceResponse $serviceResponse, $gatewayMessage)
    {
        parent::markCompleted($endStatus, $serviceResponse, $gatewayMessage);
        $this->createMessage('CreateCardResponse', $gatewayMessage);
        Helper::safeExtend($this->payment, 'onCardCreated', $serviceResponse);
    }
}
