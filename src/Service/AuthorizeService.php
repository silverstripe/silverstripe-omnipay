<?php

namespace SilverStripe\Omnipay\Service;

use SilverStripe\Omnipay\Exception\InvalidConfigurationException;
use SilverStripe\Omnipay\Exception\InvalidStateException;
use SilverStripe\Omnipay\Helper\ErrorHandling;
use SilverStripe\Omnipay\Model\Message\AuthorizedResponse;
use SilverStripe\Omnipay\Model\Message\AuthorizeError;
use SilverStripe\Omnipay\Model\Message\AuthorizeRedirectResponse;
use SilverStripe\Omnipay\Model\Message\AuthorizeRequest;
use SilverStripe\Omnipay\Model\Message\AwaitingAuthorizeResponse;
use SilverStripe\Omnipay\Model\Message\CompleteAuthorizeError;
use SilverStripe\Omnipay\Model\Message\CompleteAuthorizeRequest;

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

        $this->createMessage(AuthorizeRequest::class, $request);

        try {
            $response = $this->response = $request->send();
        } catch (\Omnipay\Common\Exception\OmnipayException $e) {
            $this->createMessage(AuthorizeError::class, $e);
            // create an error response
            return $this->generateServiceResponse(ServiceResponse::SERVICE_ERROR);
        }

        ErrorHandling::safeExtend($this, 'onAfterSendAuthorize', $request, $response);

        $serviceResponse = $this->wrapOmnipayResponse($response);

        if ($serviceResponse->isRedirect() || $serviceResponse->isAwaitingNotification()) {
            $this->payment->Status = 'PendingAuthorization';
            $this->payment->write();

            $this->createMessage(
                $serviceResponse->isRedirect() ? AuthorizeRedirectResponse::class : AwaitingAuthorizeResponse::class,
                $response
            );
        } elseif ($serviceResponse->isError()) {
            $this->createMessage(AuthorizeError::class, $response);
        } elseif ($serviceResponse->isSuccessful()) {
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

        $this->createMessage(CompleteAuthorizeRequest::class, $request);
        $response = null;
        try {
            $response = $this->response = $request->send();
        } catch (\Omnipay\Common\Exception\OmnipayException $e) {
            $this->createMessage(CompleteAuthorizeError::class, $e);

            return $this->generateServiceResponse($flags | ServiceResponse::SERVICE_ERROR);
        }

        $serviceResponse = $this->wrapOmnipayResponse($response, $isNotification);

        if ($serviceResponse->isAwaitingNotification()) {
            ErrorHandling::safeExtend($this->payment, 'onAwaitingAuthorized', $serviceResponse);
        } elseif ($serviceResponse->isError()) {
            $this->createMessage(CompleteAuthorizeError::class, $response);
        } elseif ($serviceResponse->isSuccessful()) {
            $this->markCompleted('Authorized', $serviceResponse, $response);
        }

        return $serviceResponse;
    }

    protected function markCompleted($endStatus, ServiceResponse $serviceResponse, $gatewayMessage)
    {
        parent::markCompleted($endStatus, $serviceResponse, $gatewayMessage);
        $this->createMessage(AuthorizedResponse::class, $gatewayMessage);
        ErrorHandling::safeExtend($this->payment, 'onAuthorized', $serviceResponse);
    }
}
