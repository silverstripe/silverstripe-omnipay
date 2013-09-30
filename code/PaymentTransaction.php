<?php

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