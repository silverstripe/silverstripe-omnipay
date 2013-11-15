<?php

/**
 * Payment transactions keep track of all the important things that can be logged for a payment.
 * 
 * @package payment
 */
class PaymentMessage extends DataObject{
	
	private static $db = array(
		//Created
		"Message" => "Varchar(255)",
	);

	private static $has_one = array(
		"Payment" => "Payment",
		"User" => "Member" //currently logged in user, if appliciable
	);

	public function getCMSFields(){
		return parent::getCMSFields()->makeReadOnly();
	}

}

//GatewayTransaction

class GatewayRequestTransaction extends PaymentMessage{

}

class GatewayErrorTransaction extends PaymentMessage{

}

class GatewayResponseTransaction extends PaymentMessage{

}

class PaymentComment extends PaymentMessage{

}
