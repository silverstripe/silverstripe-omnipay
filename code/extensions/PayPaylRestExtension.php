<?php

use Omnipay\PayPal\Message\RestAuthorizeResponse;
use SilverStripe\Omnipay\Service\ServiceResponse;

class PayPalRestExtension extends Extension
{
    /**
     * @param array $gatewayData
     */
    public function onBeforePurchase(array &$gatewayData)
    {
        /**
         * If you don't want to supply a Credit card then unsetting this will
         * take you through to PayPal without one
         */
        unset($gatewayData['card']);
    }

    public function onBeforeCompletePurchase(array &$gatewayData)
    {
        $gatewayData['transactionReference'] = $_GET['paymentId'];
        $gatewayData['payerId'] = $_GET['PayerID'];
    }

    /**
     * @param ServiceResponse $response
     */
    public function updateServiceResponse($response)
    {
        /** @var Payment $payment */
        $payment = $response->getPayment();

        // We only want to respond to the notification if we are using SagePay
        if ($payment->Gateway !== 'PayPal_Rest') {
            return;
        }

        $omniPayResponse = $response->getOmnipayResponse();
        if ($omnipayResponse !== null
            && $omnipayResponse->isSuccessful()
            && !$response->isError()) {

            if ($omniPayResponse instanceof RestAuthorizeResponse) {
                $payment->TransactionReference = $omniPayResponse->getTransactionId();
                $payment->write();
            }
        }
    }
}
