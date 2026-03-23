<?php

namespace SilverStripe\Omnipay\Extensions;

use Omnipay\SagePay\Message\ServerNotifyRequest;
use SilverStripe\Omnipay\Model\Message\PurchaseRedirectResponse;
use SilverStripe\Omnipay\Service\ServiceResponse;
use SilverStripe\Core\Extension;
use SilverStripe\Omnipay\Model\Message;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Service\PaymentService;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Director;

/**
 * Sagepay has some very indiviual needs so to help we have created this
 * extension that you can use as a beginning to try and help your SagePay
 * transaction to go through. Add it to your YML file e.g.
 *
 * SilverStripe\Omnipay\Service\PaymentService:
 *  extensions:
 *    - SagePayExtension
 *
 * The extension adds following:
 *  - A Description into the initial request (reequired by SagePay)
 *  - The proper transactionReference which is recieved back from SagePay
 *  - A response to the notification (See use_async_notification)
 *
 * @see https://github.com/silverstripe/silverstripe-omnipay/issues/153
 * @see https://github.com/silverstripe/silverstripe-omnipay/issues/159
 *
 * @extends Extension<PaymentService>
 */
class SagePayExtension extends Extension
{
    /** @param array<string, mixed> $gatewayData */
    public function onBeforePurchase(array &$gatewayData): void
    {
        $this->addDescription($gatewayData);
    }

    /** @param array<string, mixed> $gatewayData */
    public function onBeforeAuthorize(array &$gatewayData): void
    {
        $this->addDescription($gatewayData);
    }

    public function updateServiceResponse(ServiceResponse $response): void
    {
        $payment = $response->getPayment();

        // We only want to respond to the notification if we are using SagePay
        if ($payment->Gateway !== 'SagePay_Server') {
            return;
        }

        $this->respondToNotification($response, $payment);
    }

    /** @param array<string, mixed> $gatewayData */
    public function onBeforeCompletePurchase(array &$gatewayData): void
    {
        $this->addTransactionReference($gatewayData);
    }

    /** @param array<string, mixed> $gatewayData */
    public function onBeforeCompleteAuthorize(array &$gatewayData): void
    {
        $this->addTransactionReference($gatewayData, true);
    }

    /**
     * Grabs the transactionReference from the previous received message and adds it to the
     * gateway data, so that it can be sent back to SagePay as clarification
     * @param array<string, mixed> $gatewayData incoming gateway data
     * @param bool $isAuthorize whether or not we're dealing with a complete authorize request
     */
    private function addTransactionReference(array &$gatewayData, bool $isAuthorize = false): void
    {
        /** @var Payment $payment */
        $payment = $this->owner->getPayment();

        // Only apply the changes if the gateway is SagePay Server
        if ($payment->Gateway == 'SagePay_Server') {
            $type = ($isAuthorize) ? Message\AuthorizeRedirectResponse::class : Message\PurchaseRedirectResponse::class;

            /** @var PurchaseRedirectResponse $message */
            $message = $payment->getLatestMessageOfType($type);
            $gatewayData['transactionReference'] = $message->Reference;
        }
    }

    /**
     * Description for SagePay must be < 100 characters
     * @param array<string, mixed> $gatewayData
     */
    private function addDescription(array &$gatewayData): void
    {
        $payment = $this->owner->getPayment();
        if ($payment->Gateway == 'SagePay_Direct' || $payment->Gateway == 'SagePay_Server') {
            $gatewayData['description'] = sprintf(
                'Payment made on %s',
                date('D j M Y')
            );
        }
    }

    /**
     * Used to respond to the SagePay notification
     * @param ServiceResponse $response
     * @param Payment $payment
     */
    private function respondToNotification(ServiceResponse $response, Payment $payment): void
    {
        $omnipayResponse = $response->getOmnipayResponse();
        if ($omnipayResponse !== null
            && $omnipayResponse->isSuccessful()
            && $response->isNotification()
            && !$response->isError()
        ) {
            $msg = [
                'Status=' . ServerNotifyRequest::RESPONSE_STATUS_OK,
                'RedirectUrl=' . Director::absoluteURL($payment->SuccessUrl),
                'StatusDetail=Accepted payment ' . $payment->Identifier
            ];

            $response->setHttpResponse(HTTPResponse::create(implode(ServerNotifyRequest::LINE_SEP, $msg), 200));
        }
    }
}
