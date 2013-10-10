<?php

class DonationPage extends Page {

	private static $db = array(
		"Currency" => "Enum('USD,NZD,AUD','NZD')",
		"SuccessTitle" => "Varchar",
		"SuccessContent" => "HTMLText"
	);

	private static $has_many = array(
		"Donations" => "Donation"
	);

	public function getCMSFields(){
		$fields = parent::getCMSFields();
		$fields->addFieldsToTab("Root.SuccessContent",array(
			TextField::create("SuccessTitle"),
			HTMLEditorField::create("SuccessContent")
		));
		$fields->addFieldToTab("Root.Donations",
			GridField::create("Donations","Donations",$this->Donations(),GridFieldConfig_RecordEditor::create())
		);
		return $fields;
	}

}

class DonationPage_Controller extends Page_Controller{

	private static $allowed_actions = array('Form','complete');

	public function Form(){
		return new PaymentForm($this,"Form", 
			new FieldList(
				CurrencyField::create("Amount","Amount", 20),
				DropdownField::create("Gateway","Gateway", Payment::get_supported_gateways())
			), new FieldList(
				FormAction::create("submit")
			)
		);
	}

	public function submit($data, $form){
		$donation = new Donation();
		$form->saveInto($donation);
		$donation->ParentID = $this->ID;
		$donation->write();

		Payment::create()
			->init($data['Gateway'],$donation->Amount,$this->Currency)
			->setReturnUrl($this->Link('complete')."?donation=".$donation->ID)
			->setCancelUrl($this->Link()."?message=payment cancelled")
			->purchase($form->getData())
			->redirect();
	}

	public function complete(){

		//TODO: get donation / payment / transaction details

		return array(
			'Title' => $this->SuccessTitle,
			'Content' => $this->SuccessContent,
			'Form' => '',
			'Donation' => Donation::get()->byID($this->request->getVar('donation'))
		);
	}

}

class Donation extends DataObject {

	static $db = array(
		"Amount" => "Currency"
	);

	static $has_one = array(
		"Parent" => "DonationPage",
		"Payment" => "Payment"
	);

	static $summary_fields = array(
		"Amount","Created"
	);

}