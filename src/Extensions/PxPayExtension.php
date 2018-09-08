<?php

namespace SilverStripe\Omnipay\Extensions;

use SilverStripe\Core\Extension;

/**
 * Fix request for PxPay.
 *
 * To apply this extension use the following configuration.
 *
 * SilverStripe\Omnipay\Service\PaymentService:
 *  extensions:
 *    - SilverStripe\Omnipay\Extensions\PxPayExtension
 *
 * SilverStripe\Omnipay\PaymentGatewayController:
 *  extensions:
 *    - SilverStripe\Omnipay\Extensions\PxPayExtension
 */
class PxPayExtension extends Extension
{
    /**
     * @param array $gatewayData
     */
    public function onBeforePurchase(array &$gatewayData)
    {
        $this->fixTransactionId($gatewayData);
    }

    /**
     * @param array $gatewayData
     */
    public function onBeforeAuthorize(array &$gatewayData)
    {
        $this->fixTransactionId($gatewayData);
    }

    /**
     * @param array $gatewayData
     */
    public function onBeforeCompletePurchase(array &$gatewayData)
    {
        $this->fixTransactionId($gatewayData);
    }

    /**
     * @param array $gatewayData
     */
    public function onBeforeCompleteAuthorize(array &$gatewayData)
    {
        $this->fixTransactionId($gatewayData);
    }

    /**
     * @param HTTPRequest $request The current request
     * @param string $gateway the gateway name.
     *
     * @return Iterable
     */
    public function updatePaymentFromRequest($request, $gateway = null)
    {
        if ($gateway == 'PaymentExpress_PxPay') {
            $identifier = $request->param('Identifier');

            if ($identifier && strlen($identifier) == 16) {
                // identifier in PxPay is limited to 16 characters so can match
                // on the start as long as we have 16 characters (i.e matching
                // on `1` would not be secure).
                //
                // Identifiers are normally 30 characters.
                return Payment::get()->filter([
                    'Identifier:StartsWith' => $identifier,
                    'Gateway' => 'PaymentExpress_PxPay'
                ])->first();
            }
        }

        return null;
    }

    /**
     * @param array $gatewayData
     */
    private function fixTransactionId(array &$gatewayData)
    {
        /** @var \Payment $payment */
        $payment = $this->owner->getPayment();

        if ($payment->Gateway == 'PaymentExpress_PxPay') {
            $gatewayData['transactionId'] = substr($gatewayData['transactionId'], 0, 16);
        }
    }
}
