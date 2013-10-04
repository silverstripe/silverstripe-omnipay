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

	/**
	 * Only allow setting identifier, if one doesn't exist yet.
	 * @param string $id identifier
	 */
	function setIdentifier($id){
		if(!$this->Identifier){
			$this->setField('Identifier', $id);
		}
	}

	/**
	 * Generate a unique url-friendly identifier, if one doesn't exist yet.
	 * @return string|null the new identifier, if created.
	 */
	public function generateIdentifier(){
		if(!$this->Identifier){
			$id = $this->PaymentID.uniqid();
			while(PaymentTransaction::get()->filter('Identifier',$id)->exists()){
				$id = $this->PaymentID.uniqid();
			}
			$this->Identifier = $id;
			return $id;
		}
		return null;
	}

}