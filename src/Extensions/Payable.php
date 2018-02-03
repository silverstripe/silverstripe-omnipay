<?php

namespace SilverStripe\Omnipay\Extensions;

use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\HasManyList;

/**
 * An extension for providing payments on a particular data object.
 *
 * @method Payment[]|HasManyList Payments()
 */
class Payable extends DataExtension
{
    private static $has_many = [
        'Payments' => Payment::class
    ];

    /**
     * Get the total captured amount
     *
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
     *
     * @return float
     */
    public function TotalPaidOrAuthorized()
    {
        $paid = 0;

        if ($payments = $this->owner->Payments()) {
            foreach ($payments as $payment) {
                // Captured and authorized payments
                // (which aren't manual) should count towards the total
                $captured = $payment->Status == 'Captured';
                $authorized = $payment->Status == 'Authorized' && !GatewayInfo::isManual($payment->Gateway);

                if ($captured || $authorized) {
                    $paid += $payment->Amount;
                }
            }
        }

        return $paid;
    }

    /**
     * Whether or not the model has payments that are in a pending state.
     *
     * Can be used to show a waiting screen to the user or similar.
     *
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
