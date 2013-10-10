<?php

class Payable extends DataExtension {


	private static $has_many = array(
		'PaymentTransactions' => 'PaymentTransaction'
	);

	public function updateCMS(){

		// add grid field with payments
	}

	/**
	 * [createPayment description]
	 * @param  [type] $amount   Amount to get payment for
	 * @param  [type] $currency [description]
	 * @param  [type] $gateway  [description]
	 * @return [type]           [description]
	 */
	public function createPayment($amount, $currency, $gateway){

	}

	public function getTotalPaid(){

	}


}