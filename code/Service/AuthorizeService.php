<?php

namespace SilverStripe\Omnipay\Service;


use SilverStripe\Omnipay\Exception\InvalidStateException;
use SilverStripe\Omnipay\Exception\InvalidConfigurationException;

class AuthorizeService extends PaymentService
{
    /**
     * If the return URL wasn't explicitly set, get it from the last AuthorizeRequest message
     * @return string
     */
    public function getReturnUrl()
    {
        $value = parent::getReturnUrl();
        if (!$value && $this->payment->isInDB()) {
            $msg = $this->payment->getLatestMessageOfType('AuthorizeRequest');
            $value = $msg ? $msg->SuccessURL : \Director::baseURL();
        }
        return $value;
    }

    /**
     * If the cancel URL wasn't explicitly set, get it from the last AuthorizeRequest message
     * @return string
     */
    public function getCancelUrl()
    {
        $value = parent::getCancelUrl();
        if (!$value && $this->payment->isInDB()) {
            $msg = $this->payment->getLatestMessageOfType('AuthorizeRequest');
            $value = $msg ? $msg->FailureURL : \Director::baseURL();
        }
        return $value;
    }

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
        if(!$gateway->supportsAuthorize()){
            throw new InvalidConfigurationException(
                sprintf('The gateway "%s" doesn\'t support authorize', $this->payment->Gateway)
            );
        }

        $gatewayData = $this->gatherGatewayData($data);

        $this->extend('onBeforeAuthorize', $gatewayData);
        $request = $this->oGateway()->authorize($gatewayData);
        $this->extend('onAfterAuthorize', $request);

        $message = $this->createMessage('AuthorizeRequest', $request);
        $message->SuccessURL = $this->returnUrl;
        $message->FailureURL = $this->cancelUrl;
        $message->write();

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
        } else if($serviceResponse->isError()){
            $this->createMessage('AuthorizeError', $response);
        } else {
            $this->createMessage('AuthorizedResponse', $response);
            $this->payment->Status = 'Authorized';
            $this->payment->write();
            $this->payment->extend('onAuthorized', $serviceResponse);
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
        if($this->payment->Status === 'Authorized'){
            return $this->generateServiceResponse($flags);
        }

        if($this->payment->Status !== 'PendingAuthorization'){
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

        if($serviceResponse->isError()) {
            $this->createMessage('CompleteAuthorizeError', $response);
            return $serviceResponse;
        }

        if(!$serviceResponse->isAwaitingNotification()){
            $this->createMessage('AuthorizedResponse', $response);
            $this->payment->Status = 'Authorized';
            $this->payment->write();
            $this->payment->extend('onAuthorized', $serviceResponse);
        } else {
            $this->payment->extend('onAwaitingAuthorized', $serviceResponse);
        }


        return $serviceResponse;
    }
}
