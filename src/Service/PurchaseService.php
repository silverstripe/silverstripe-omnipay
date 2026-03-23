<?php

namespace SilverStripe\Omnipay\Service;

use SilverStripe\Omnipay\Exception\InvalidStateException;
use SilverStripe\Omnipay\Exception\InvalidConfigurationException;
use SilverStripe\Omnipay\Helper\ErrorHandling;

class PurchaseService extends PaymentService
{
    public const MESSAGE_PURCHASE_REQUEST = 'PurchaseRequest';

    public const MESSAGE_PURCHASE_ERROR = 'PurchaseError';

    public const MESSAGE_PURCHASE_REDIRECT_RESPONSE = 'PurchaseRedirectResponse';

    public const MESSAGE_AWAITING_PURCHASE_RESPONSE = 'AwaitingPurchaseResponse';

    public const MESSAGE_COMPLETE_PURCHASE_REQUEST = 'CompletePurchaseRequest';

    public const MESSAGE_COMPLETE_PURCHASE_ERROR = 'CompletePurchaseError';

    public const MESSAGE_PURCHASED_RESPONSE = 'PurchasedResponse';

    /** @var list<string> */
    public const ERROR_MESSAGE_TYPES = [
        self::MESSAGE_PURCHASE_ERROR,
        self::MESSAGE_COMPLETE_PURCHASE_ERROR,
    ];

    /**
     * Attempt to make a payment.
     *
     * @param array<string, mixed> $data returnUrl/cancelUrl + customer creditcard and billing/shipping details.
     *  Some keys (e.g. "amount") are overwritten with data from the associated {@link $payment}.
     *  If this array is constructed from user data (e.g. a form submission), please take care
     *  to whitelist accepted fields, in order to ensure sensitive gateway parameters like "freeShipping" can't be set.
     *  If using {@link Form->getData()}, only fields which exist in the form are returned,
     *  effectively whitelisting against arbitrary user input.
     */
    public function initiate(array $data = []): ServiceResponse
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

        $this->createMessage(self::MESSAGE_PURCHASE_REQUEST, $request);

        try {
            $response = $this->response = $request->send();
        } catch (\Omnipay\Common\Exception\OmnipayException $e) {
            $this->createMessage(self::MESSAGE_PURCHASE_ERROR, $e);
            // create an error response
            return $this->generateServiceResponse(ServiceResponse::SERVICE_ERROR);
        }

        ErrorHandling::safeExtend($this, 'onAfterSendPurchase', $request, $response);

        $serviceResponse = $this->wrapOmnipayResponse($response);

        if ($serviceResponse->isRedirect() || $serviceResponse->isAwaitingNotification()) {
            $this->payment->Status = 'PendingPurchase';
            $this->payment->write();

            $this->createMessage(
                $serviceResponse->isRedirect()
                    ? self::MESSAGE_PURCHASE_REDIRECT_RESPONSE
                    : self::MESSAGE_AWAITING_PURCHASE_RESPONSE,
                $response
            );
        } elseif ($serviceResponse->isError()) {
            $this->createMessage(self::MESSAGE_PURCHASE_ERROR, $response);
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
    public function complete(array $data = [], bool $isNotification = false): ServiceResponse
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

        $this->createMessage(self::MESSAGE_COMPLETE_PURCHASE_REQUEST, $request);
        $response = null;
        try {
            $response = $this->response = $request->send();
        } catch (\Omnipay\Common\Exception\OmnipayException $e) {
            $this->createMessage(self::MESSAGE_COMPLETE_PURCHASE_ERROR, $e);
            return $this->generateServiceResponse($flags | ServiceResponse::SERVICE_ERROR);
        }

        $serviceResponse = $this->wrapOmnipayResponse($response, $isNotification);

        if ($serviceResponse->isAwaitingNotification()) {
            ErrorHandling::safeExtend($this->payment, 'onAwaitingCaptured', $serviceResponse);
        } elseif ($serviceResponse->isError()) {
            $this->createMessage(self::MESSAGE_COMPLETE_PURCHASE_ERROR, $response);
        } elseif ($serviceResponse->isSuccessful()) {
            $this->markCompleted('Captured', $serviceResponse, $response);
        }

        return $serviceResponse;
    }

    protected function markCompleted(string $endStatus, ServiceResponse $serviceResponse, mixed $gatewayMessage): void
    {
        parent::markCompleted($endStatus, $serviceResponse, $gatewayMessage);
        $this->createMessage(self::MESSAGE_PURCHASED_RESPONSE, $gatewayMessage);
        ErrorHandling::safeExtend($this->payment, 'onCaptured', $serviceResponse);
    }
}
