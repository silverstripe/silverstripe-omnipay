<?php

namespace SilverStripe\Omnipay\Service;

use SilverStripe\Omnipay\Exception\InvalidStateException;
use SilverStripe\Omnipay\Exception\InvalidConfigurationException;
use SilverStripe\Omnipay\Helper\ErrorHandling;
use SilverStripe\Omnipay\Model\Message\AwaitingPurchaseResponse;
use SilverStripe\Omnipay\Model\Message\CompletePurchaseError;
use SilverStripe\Omnipay\Model\Message\CompletePurchaseRequest;
use SilverStripe\Omnipay\Model\Message\PurchasedResponse;
use SilverStripe\Omnipay\Model\Message\PurchaseError;
use SilverStripe\Omnipay\Model\Message\PurchaseRedirectResponse;
use SilverStripe\Omnipay\Model\Message\PurchaseRequest;

class PurchaseService extends PaymentService
{
    /**
     * Attempt to make a payment.
     *
     * @inheritdoc
     * @param  array $data returnUrl/cancelUrl + customer creditcard and billing/shipping details.
     *  Some keys (e.g. "amount") are overwritten with data from the associated {@link $payment}.
     *  If this array is constructed from user data (e.g. a form submission), please take care
     *  to whitelist accepted fields, in order to ensure sensitive gateway parameters like "freeShipping" can't be set.
     *  If using {@link Form->getData()}, only fields which exist in the form are returned,
     *  effectively whitelisting against arbitrary user input.
     */
    public function initiate($data = array())
    {
        if ($this->payment->Status !== 'Created') {
            throw new InvalidStateException('Cannot initiate a purchase with this payment. Status is not "Created"');
        }

        if (!$this->payment->isInDB()) {
            $this->payment->write();
        }

        $gateway = $this->oGateway();
        if (!$gateway->supportsPurchase()) {
            throw new InvalidConfigurationException(
                sprintf('The gateway "%s" doesn\'t support purchase', $this->payment->Gateway)
            );
        }

        $gatewayData = $this->gatherGatewayData($data);

        $this->extend('onBeforePurchase', $gatewayData);
        $request = $this->oGateway()->purchase($gatewayData);
        $this->extend('onAfterPurchase', $request);

        $this->createMessage(PurchaseRequest::class, $request);

        try {
            $response = $this->response = $request->send();
        } catch (\Omnipay\Common\Exception\OmnipayException $e) {
            $this->createMessage(PurchaseError::class, $e);
            // create an error response
            return $this->generateServiceResponse(ServiceResponse::SERVICE_ERROR);
        }

        ErrorHandling::safeExtend($this, 'onAfterSendPurchase', $request, $response);

        $serviceResponse = $this->wrapOmnipayResponse($response);

        if ($serviceResponse->isRedirect() || $serviceResponse->isAwaitingNotification()) {
            $this->payment->Status = 'PendingPurchase';
            $this->payment->write();

            $this->createMessage(
                $serviceResponse->isRedirect() ? PurchaseRedirectResponse::class : AwaitingPurchaseResponse::class,
                $response
            );
        } elseif ($serviceResponse->isError()) {
            $this->createMessage(PurchaseError::class, $response);
        } elseif ($serviceResponse->isSuccessful()) {
            $this->markCompleted('Captured', $serviceResponse, $response);
        }

        return $serviceResponse;
    }

    /**
     * Finalise this payment, after off-site external processing.
     * This is usually only called by PaymentGatewayController.
     * @inheritdoc
     */
    public function complete($data = array(), $isNotification = false)
    {
        $flags = $isNotification ? ServiceResponse::SERVICE_NOTIFICATION : 0;
        // The payment is already captured
        if ($this->payment->Status === 'Captured') {
            return $this->generateServiceResponse($flags);
        }

        if ($this->payment->Status !== 'PendingPurchase') {
            throw new InvalidStateException('Cannot complete this payment. Status is not "PendingPurchase"');
        }

        $gateway = $this->oGateway();
        if (!$gateway->supportsCompletePurchase()) {
            throw new InvalidConfigurationException(
                sprintf('The gateway "%s" doesn\'t support completePurchase', $this->payment->Gateway)
            );
        }

        // purchase and completePurchase should use the same data
        $gatewayData = $this->gatherGatewayData($data);

        $this->extend('onBeforeCompletePurchase', $gatewayData);
        $request = $gateway->completePurchase($gatewayData);
        $this->extend('onAfterCompletePurchase', $request);

        $this->createMessage(CompletePurchaseRequest::class, $request);
        $response = null;
        try {
            $response = $this->response = $request->send();
        } catch (\Omnipay\Common\Exception\OmnipayException $e) {
            $this->createMessage(CompletePurchaseError::class, $e);
            return $this->generateServiceResponse($flags | ServiceResponse::SERVICE_ERROR);
        }

        $serviceResponse = $this->wrapOmnipayResponse($response, $isNotification);

        if ($serviceResponse->isAwaitingNotification()) {
            ErrorHandling::safeExtend($this->payment, 'onAwaitingCaptured', $serviceResponse);
        } elseif ($serviceResponse->isError()) {
            $this->createMessage(CompletePurchaseError::class, $response);
        } elseif ($serviceResponse->isSuccessful()) {
            $this->markCompleted('Captured', $serviceResponse, $response);
        }

        return $serviceResponse;
    }

    protected function markCompleted($endStatus, ServiceResponse $serviceResponse, $gatewayMessage)
    {
        parent::markCompleted($endStatus, $serviceResponse, $gatewayMessage);
        $this->createMessage(PurchasedResponse::class, $gatewayMessage);
        ErrorHandling::safeExtend($this->payment, 'onCaptured', $serviceResponse);
    }
}
