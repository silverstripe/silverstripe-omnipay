<?php

/**
 * An extension for providing payments on a particular data object.
 *
 * @package payment
 */
class Payable extends DataExtension {

	private static $has_many = array(
		'Payments' => 'Payment'
	);

	public function updateCMSFields(FieldList $fields) {
		$fields->addFieldToTab("Root.Payments",
			GridField::create("Payments", "Payments", $this->owner->Payments(),
				GridFieldConfig_RecordEditor::create()
					->removeComponentsByType('GridFieldAddNewButton')
					->removeComponentsByType('GridFieldDeleteAction')
					->removeComponentsByType('GridFieldFilterHeader')
					->removeComponentsByType('GridFieldPageCount')
			)
		);
	}

	public function TotalPaid() {
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

}
