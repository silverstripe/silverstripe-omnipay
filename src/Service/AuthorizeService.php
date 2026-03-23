<?php

namespace SilverStripe\Omnipay\Service;

use SilverStripe\Omnipay\Exception\InvalidConfigurationException;
use SilverStripe\Omnipay\Exception\InvalidStateException;
use SilverStripe\Omnipay\Helper\ErrorHandling;

class AuthorizeService extends PaymentService
{
    public const MESSAGE_AUTHORIZE_REQUEST = 'AuthorizeRequest';

    public const MESSAGE_AUTHORIZE_ERROR = 'AuthorizeError';

    public const MESSAGE_AUTHORIZE_REDIRECT_RESPONSE = 'AuthorizeRedirectResponse';

    public const MESSAGE_AWAITING_AUTHORIZE_RESPONSE = 'AwaitingAuthorizeResponse';

    public const MESSAGE_COMPLETE_AUTHORIZE_REQUEST = 'CompleteAuthorizeRequest';

    public const MESSAGE_COMPLETE_AUTHORIZE_ERROR = 'CompleteAuthorizeError';

    public const MESSAGE_AUTHORIZED_RESPONSE = 'AuthorizedResponse';

    /** @var list<string> */
    public const ERROR_MESSAGE_TYPES = [
        self::MESSAGE_AUTHORIZE_ERROR,
        self::MESSAGE_COMPLETE_AUTHORIZE_ERROR,
    ];

    /**
     * Start an authorization request
     *
     * @param array<string, mixed> $data
     */
    public function initiate(array $data = []): ServiceResponse
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

        $this->createMessage(self::MESSAGE_AUTHORIZE_REQUEST, $request);

        try {
            $response = $this->response = $request->send();
        } catch (\Omnipay\Common\Exception\OmnipayException $e) {
            $this->createMessage(self::MESSAGE_AUTHORIZE_ERROR, $e);
            // create an error response
            return $this->generateServiceResponse(ServiceResponse::SERVICE_ERROR);
        }

        ErrorHandling::safeExtend($this, 'onAfterSendAuthorize', $request, $response);

        $serviceResponse = $this->wrapOmnipayResponse($response);

        if ($serviceResponse->isRedirect() || $serviceResponse->isAwaitingNotification()) {
            $this->payment->Status = 'PendingAuthorization';
            $this->payment->write();

            $this->createMessage(
                $serviceResponse->isRedirect()
                    ? self::MESSAGE_AUTHORIZE_REDIRECT_RESPONSE
                    : self::MESSAGE_AWAITING_AUTHORIZE_RESPONSE,
                $response
            );
        } elseif ($serviceResponse->isError()) {
            $this->createMessage(self::MESSAGE_AUTHORIZE_ERROR, $response);
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
    public function complete(array $data = [], bool $isNotification = false): ServiceResponse
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

        $this->createMessage(self::MESSAGE_COMPLETE_AUTHORIZE_REQUEST, $request);
        $response = null;
        try {
            $response = $this->response = $request->send();
        } catch (\Omnipay\Common\Exception\OmnipayException $e) {
            $this->createMessage(self::MESSAGE_COMPLETE_AUTHORIZE_ERROR, $e);

            return $this->generateServiceResponse($flags | ServiceResponse::SERVICE_ERROR);
        }

        $serviceResponse = $this->wrapOmnipayResponse($response, $isNotification);

        if ($serviceResponse->isAwaitingNotification()) {
            ErrorHandling::safeExtend($this->payment, 'onAwaitingAuthorized', $serviceResponse);
        } elseif ($serviceResponse->isError()) {
            $this->createMessage(self::MESSAGE_COMPLETE_AUTHORIZE_ERROR, $response);
        } elseif ($serviceResponse->isSuccessful()) {
            $this->markCompleted('Authorized', $serviceResponse, $response);
        }

        return $serviceResponse;
    }

    protected function markCompleted(string $endStatus, ServiceResponse $serviceResponse, mixed $gatewayMessage): void
    {
        parent::markCompleted($endStatus, $serviceResponse, $gatewayMessage);
        $this->createMessage(self::MESSAGE_AUTHORIZED_RESPONSE, $gatewayMessage);
        ErrorHandling::safeExtend($this->payment, 'onAuthorized', $serviceResponse);
    }
}
