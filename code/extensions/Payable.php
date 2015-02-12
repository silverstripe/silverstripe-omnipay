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

	/**
	 * Get the total sum of all captured payments.
	 */
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

	/**
	 * Get a history of payments
	 * Excluding "Created" payments.
	 */
	public function PaymentHistory() {
		return $this->owner->Payments()
			->filter("Status:not", array("Created"));
	}

	/**
	 * Get the most recently started payment
	 */
	public function LastPayment() {
		return $this->owner->Payments()
			->sort("Created", "DESC")
			->first();
	}

	/**
	 * Get the most recenty captured payment
	 */
	public function LastCapturedPayment() {
		return $this->owner->Payments()
			->filter("Status", "Captured")
			->sort("Created", "DESC")
			->first();
	}

	/**
	 * Cancel any payments that have not completed.
	 */
	public function tidyPayments() {
		foreach($this->Payments() as $payment){
			if(!$payment->isComplete()){
				$response = PurchaseService::create($payment)
					->cancelPurchase();
			}
		}
	}

}
