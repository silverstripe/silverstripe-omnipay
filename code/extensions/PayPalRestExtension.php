<?php

use Omnipay\PayPal\Message\RestAuthorizeResponse;
use SilverStripe\Omnipay\Service\ServiceResponse;

/**
 * PayPal Rest can work without taking a credit card, this extension deals with
 * that and the response that comes back from PayPal
 *
 * # payment.yml
 * SilverStripe\Omnipay\Service\PaymentService:
 *  extensions:
 *    - PayPalRestExtension
 */
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
        /**
         * As described in Omnipay\PayPal\Message\RestPurchaseRequest PayPal
         * responds from the payment withthe transaction reference and a
         * PayerID as GET vars. We gather tham and throw them back to PayPal
         * for confirmation
         */
        $gatewayData['transactionReference'] = $_GET['paymentId'];
        $gatewayData['payerId'] = $_GET['PayerID'];
    }
}
