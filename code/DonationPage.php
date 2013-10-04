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

		$payment = Payment::create()
					->setGateway($data['Gateway'])
					->setAmount($donation->Amount)
					->setCurrency($this->Currency);
		$payment->write();

		$response = $payment->purchase(array(
				'returnUrl' => $this->Link('complete'),
				'cancelUrl' => $this->Link()
			),
			$form->getData()
		);

		if ($response->isSuccessful()) {
			$this->redirect(
				$this->Link('complete')
			); // payment was successful
			return;
		} elseif ($response->isRedirect()) { // redirect to off-site payment gateway
			$this->redirect(
				$response->getRedirectUrl()
			);
			return;
		} else { // payment failed: display message to customer
			$form->sessionMessage($response->getMessage());
			$this->redirectBack();
		}
		return;
	}

	public function complete(){
		return array(
			'Title' => $this->SuccessTitle,
			'Content' => $this->SuccessContent,
			'Form' => ''
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