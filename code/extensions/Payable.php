<?php

use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\Admin\GridField\GridFieldCaptureAction;
use SilverStripe\Omnipay\Admin\GridField\GridFieldRefundAction;
use SilverStripe\Omnipay\Admin\GridField\GridFieldVoidAction;

/**
 * An extension for providing payments on a particular data object.
 *
 * @package payment
 */
class Payable extends DataExtension {

	private static $has_many = array(
		'Payments' => 'Payment'
	);

	public function updateCMSFields(FieldList $fields)
    {
        $gridConfig = GridFieldConfig_RecordEditor::create()
            ->addComponent(new GridFieldCaptureAction(), 'GridFieldEditButton')
            ->addComponent(new GridFieldRefundAction(), 'GridFieldEditButton')
            ->addComponent(new GridFieldVoidAction(), 'GridFieldEditButton')
            ->removeComponentsByType('GridFieldAddNewButton')
            ->removeComponentsByType('GridFieldDeleteAction')
            ->removeComponentsByType('GridFieldFilterHeader')
            ->removeComponentsByType('GridFieldPageCount');

		$fields->addFieldToTab('Root.Payments',
			GridField::create('Payments', _t('Payment.PLURALNAME','Payments'), $this->owner->Payments(), $gridConfig)
		);
	}

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

}
