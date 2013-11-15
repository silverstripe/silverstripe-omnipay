<?php

/**
 * GatewayTransaction DataObject
 *
 * Gateway transaction details are a database record of interactions
 * with the gateway. It allows a detailed history of a payment.
 *
 * @package payment
 */
final class GatewayTransaction extends PaymentMessage{
	
	private static $db = array(
		"Gateway" => "Varchar",
		"Type" => "Enum('Purchase,CompletePurchase,Authorize,CompleteAuthorize,Capture,Refund,Void')",
		"Identifier" => "Varchar", //local id
		"Reference" => "Varchar", //remote id
		"Code" => "Varchar"
	);

	private static $summary_fields = array(
		'Type','Identifier','Reference','Message','Code'
	);

	/**
	 * Only allow setting identifier, if one doesn't exist yet.
	 * @param string $id identifier
	 */
	public function setIdentifier($id){
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
			while(GatewayTransaction::get()->filter('Identifier',$id)->exists()){
				$id = $this->PaymentID.uniqid();
			}
			$this->Identifier = $id;
			return $id;
		}
		return null;
	}

}
