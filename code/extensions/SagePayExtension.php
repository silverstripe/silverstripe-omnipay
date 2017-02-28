<?php

use Omnipay\SagePay\Message\ServerNotifyResponse;
use SilverStripe\Omnipay\Service\ServiceResponse;

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
 * @see https://github.com/silverstripe/silverstripe-omnipay/issues/153
 * @see https://github.com/silverstripe/silverstripe-omnipay/issues/159
 */
class SagePayExtension extends Extension
{
    /**
     * @param array $gatewayData
     */
    public function onBeforePurchase(array &$gatewayData)
    {
        $this->addDescription($gatewayData);
    }

    /**
     * @param array $gatewayData
     */
    public function onBeforeAuthorize(array &$gatewayData)
    {
        $this->addDescription($gatewayData);
    }

    /**
     * @param ServiceResponse $response
     */
    public function updateServiceResponse($response)
    {
        $payment = $response->getPayment();

        // We only want to respond to the notification if we are using SagePay
        if ($payment->Gateway !== 'SagePay_Server') {
            return;
        }

        $this->respondToNotification($response, $payment);
    }

    /**
     * @param array $gatewayData
     */
    public function onBeforeCompletePurchase(array &$gatewayData)
    {
        $gatewayData['transactionReference'] = $this->getTransactionReference($gatewayData);
    }

    /**
     * Grabs the transactionReference from the previous received message so
     * that it can be sent back to SagePay as clarification
     * @return string
     */
    private function getTransactionReference()
    {
        /** @var \Payment $payment */
        $payment = $this->owner->getPayment();

        /** @var PurchaseRedirectResponse $message */
        $message = $payment->getLatestMessageOfType('PurchaseRedirectResponse');
        return $message->Reference;
    }

    /**
     * Description for Sagepay must be < 100 characters
     * @param $gatewayData
     */
    private function addDescription(array &$gatewayData)
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
     * Used to respond to the SagepPay notication
     * @param ServiceResponse $response
     * @param Payment $payment
     */
    private function respondToNotification(ServiceResponse $response, Payment $payment)
    {
        $omnipayResponse = $response->getOmnipayResponse();
        if ($omnipayResponse !== null
            && $omnipayResponse->isSuccessful()
            && $response->isNotification()
            && !$response->isError()
        ) {
            $msg = [
                'Status=' . ServerNotifyResponse::RESPONSE_STATUS_OK,
                'RedirectUrl=' . Director::absoluteURL($payment->SuccessUrl),
                'StatusDetail=Accepted payment ' . $payment->Identifier
            ];
            $response->setHttpResponse(new SS_HTTPResponse(implode(ServerNotifyResponse::LINE_SEP, $msg), 200));
        }
    }
}
