<?php

/**
 * Base class for logging messages, transactions etc associated with a payment.
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

	private static $summary_fields = array(
		'ClassName', 'Message', 'User.Name'
	);

	public function getCMSFields(){
		return parent::getCMSFields()->makeReadOnly();
	}

	public function onBeforeWrite(){
		parent::onBeforeWrite();
		//automatically set the current user id for new payment messages
		if(!$this->UserID && !$this->isInDB()){
			$this->UserID = Member::currentUserID();
		}
	}

}

class GatewayMessage extends PaymentMessage{
	
	private static $db = array(
		"Gateway" => "Varchar",
		"Identifier" => "Varchar", //local id
		"Reference" => "Varchar", //remote id
		"Code" => "Varchar"
	);

	private static $summary_fields = array(
		'Type','Identifier','Reference','Message','Code'
	);

	private static $indexes = array(
		'Identifier' => true,
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
			while(GatewayMessage::get()->filter('Identifier',$id)->exists()){
				$id = $this->PaymentID.uniqid();
			}
			$this->Identifier = $id;
			return $id;
		}
		return null;
	}

}

class GatewayRequestMessage extends GatewayMessage{}
class GatewayResponseMessage extends GatewayMessage{}
class GatewayRedirectResponseMessage extends GatewayMessage{}
class GatewayErrorMessage extends GatewayMessage{}
