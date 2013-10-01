<?php

/**
 * PaymentTransaction DataObject
 *
 * This class is used for storing payment transaction details.
 * It provides a more detailed history of a payment's history.
 */
class PaymentTransaction extends DataObject{
	
	private static $db = array(
		"Type" => "Enum('Purchase,Authorize,Capture,Refund,Void')",
		"Identifier" => "Varchar", //local id
		"Reference" => "Varchar", //remote id
		"Message" => "Varchar",
		"Code" => "Varchar",
		// "Success" => "Boolean",
		// "Redirect" => "Boolean"
	);

	private static $has_one = array(
		"Payment" => "Payment"
	);

	function generateIdentifier(){
		//while...
	}

}