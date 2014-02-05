<?php

class VoidService extends PaymentService{

	/**
	 * Cancel this payment, and prevent any future changes.
	 * @return PaymentResponse encapsulated response info
	 */
	public function void() {
		//TODO: call gateway function, if available
		$this->payment->Status = "Void";
		$this->payment->write();
	}

}
