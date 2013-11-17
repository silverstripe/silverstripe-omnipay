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

class PurchaseRequest extends GatewayRequestMessage{}
class PurchasedResponse extends GatewayResponseMessage{}
class PurchaseRedirectResponse extends GatewayRedirectResponseMessage{}
class PurchaseError extends GatewayErrorMessage{}

class CompletePurchaseRequest extends GatewayRequestMessage{}
//PurchasedResponse
class CompletePurchaseError extends GatewayErrorMessage{}

class AuthorizeRequest extends GatewayRequestMessage{}
class AuthorizedResponse extends GatewayResponseMessage{}
class AuthorizeRedirectResponse extends GatewayRedirectResponseMessage{}
class AuthorizeError extends GatewayErrorMessage{}

class CompleteAuthorizeRequest extends GatewayRequestMessage{}
//AuthorizedResponse
class CompleteAuthorizeError extends GatewayErrorMessage{}

class CaptureRequest extends GatewayRequestMessage{}
class CapturedResponse extends GatewayResponseMessage{}
class CaptureError extends GatewayErrorMessage{}

class RefundRequest extends GatewayRequestMessage{}
class RefundedResponse extends GatewayResponseMessage{}
class RefundError extends GatewayErrorMessage{}

class VoidRequest extends GatewayRequestMessage{}
class VoidedResponse extends GatewayResponseMessage{}
class VoidError extends GatewayErrorMessage{}