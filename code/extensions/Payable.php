<?php

use SilverStripe\Omnipay\GatewayInfo;

/**
 * An extension for providing payments on a particular data object.
 *
 * @package payment
 */
class Payable extends DataExtension
{

    private static $has_many = array(
        'Payments' => 'Payment'
    );

    /**
     * Get the total captured amount
     * @return float
     */
    public function TotalPaid()
    {
        $paid = 0;
        if ($payments = $this->owner->Payments()) {
            foreach ($payments as $payment) {
                if ($payment->Status == 'Captured') {
                    $paid += $payment->Amount;
                }
            }
        }
        return $paid;
    }

    /**
     * Get the total captured or authorized amount, excluding Manual payments.
     * @return float
     */
    public function TotalPaidOrAuthorized()
    {
        $paid = 0;
        if ($payments = $this->owner->Payments()) {
            foreach ($payments as $payment) {
                // Captured and authorized payments (which aren't manual) should count towards the total
                if (
                    $payment->Status == 'Captured' ||
                    ($payment->Status == 'Authorized' && !GatewayInfo::isManual($payment->Gateway))
                ) {
                    $paid += $payment->Amount;
                }
            }
        }
        return $paid;
    }

    /**
     * Whether or not the model has payments that are in a pending state.
     * Can be used to show a waiting screen to the user or similar.
     * @return bool
     */
    public function HasPendingPayments()
    {
        return $this->owner->Payments()
            ->filter('Status', array(
                'PendingAuthorization',
                'PendingPurchase',
                'PendingCapture',
                'PendingRefund',
                'PendingVoid'
            ))->count() > 0;
    }
}
