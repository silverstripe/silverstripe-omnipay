<?php

namespace SilverStripe\Omnipay\Service;

use SilverStripe\Omnipay\Exception\InvalidStateException;
use SilverStripe\Omnipay\Exception\InvalidConfigurationException;

class AuthorizeService extends PaymentService
{
    /**
     * Start an authorization request
     *
     * @inheritdoc
     */
    public function initiate($data = array())
    {
        if ($this->payment->Status !== 'Created') {
            throw new InvalidStateException('Cannot authorize this payment. Status is not "Created"');
        }

        if (!$this->payment->isInDB()) {
            $this->payment->write();
        }

        $gateway = $this->oGateway();
        if (!$gateway->supportsAuthorize()) {
            throw new InvalidConfigurationException(
                sprintf('The gateway "%s" doesn\'t support authorize', $this->payment->Gateway)
            );
        }

        $gatewayData = $this->gatherGatewayData($data);

        $this->extend('onBeforeAuthorize', $gatewayData);
        $request = $this->oGateway()->authorize($gatewayData);
        $this->extend('onAfterAuthorize', $request);

        $this->createMessage('AuthorizeRequest', $request);

        try {
            $response = $this->response = $request->send();
        } catch (\Omnipay\Common\Exception\OmnipayException $e) {
            $this->createMessage('AuthorizeError', $e);
            // create an error response
            return $this->generateServiceResponse(ServiceResponse::SERVICE_ERROR);
        }

        $this->extend('onAfterSendAuthorize', $request, $response);

        $serviceResponse = $this->wrapOmnipayResponse($response);

        if ($serviceResponse->isRedirect() || $serviceResponse->isAwaitingNotification()) {
            $this->payment->Status = 'PendingAuthorization';
            $this->payment->write();

            $this->createMessage(
                $serviceResponse->isRedirect() ? 'AuthorizeRedirectResponse' : 'AwaitingAuthorizeResponse',
                $response
            );
        } elseif ($serviceResponse->isError()) {
            $this->createMessage('AuthorizeError', $response);
        } else {
            $this->markCompleted('Authorized', $serviceResponse, $response);
        }

        return $serviceResponse;
    }

    /**
     * Finalise this authorization, after off-site external processing.
     * This is usually only called by PaymentGatewayController.
     * @inheritdoc
     */
    public function complete($data = array(), $isNotification = false)
    {
        $flags = $isNotification ? ServiceResponse::SERVICE_NOTIFICATION : 0;

        // The payment is already captured
        if ($this->payment->Status === 'Authorized') {
            return $this->generateServiceResponse($flags);
        }

        if ($this->payment->Status !== 'PendingAuthorization') {
            throw new InvalidStateException('Cannot complete this payment. Status is not "PendingAuthorization"');
        }

        $gateway = $this->oGateway();
        if (!$gateway->supportsCompleteAuthorize()) {
            throw new InvalidConfigurationException(
                sprintf('The gateway "%s" doesn\'t support completeAuthorize', $this->payment->Gateway)
            );
        }

        // purchase and completePurchase should use the same data
        $gatewayData = $this->gatherGatewayData($data);

        $this->extend('onBeforeCompleteAuthorize', $gatewayData);
        $request = $gateway->completeAuthorize($gatewayData);
        $this->extend('onAfterCompleteAuthorize', $request);

        $this->createMessage('CompleteAuthorizeRequest', $request);
        $response = null;
        try {
            $response = $this->response = $request->send();
        } catch (\Omnipay\Common\Exception\OmnipayException $e) {
            $this->createMessage('CompleteAuthorizeError', $e);
            return $this->generateServiceResponse($flags | ServiceResponse::SERVICE_ERROR);
        }

        $serviceResponse = $this->wrapOmnipayResponse($response, $isNotification);

        if ($serviceResponse->isError()) {
            $this->createMessage('CompleteAuthorizeError', $response);
            return $serviceResponse;
        }

        if (!$serviceResponse->isAwaitingNotification()) {
            $this->markCompleted('Authorized', $serviceResponse, $response);
        } else {
            $this->payment->extend('onAwaitingAuthorized', $serviceResponse);
        }

        return $serviceResponse;
    }

    protected function markCompleted($endStatus, ServiceResponse $serviceResponse, $gatewayMessage)
    {
        parent::markCompleted($endStatus, $serviceResponse, $gatewayMessage);
        $this->createMessage('AuthorizedResponse', $gatewayMessage);
        $this->payment->extend('onAuthorized', $serviceResponse);
    }
}
