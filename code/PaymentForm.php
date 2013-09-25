<?php

class PaymentForm extends Form{


	function __construct($controller, $name = "PaymentForm", $fields, $actions){

		$newfields = $this->getFields();
		$newfields->merge($fields);

		//TODO: validator?
		
		parent::__construct($controller, $name, $newfields, $actions);

		$this->dummyData();

	}

	protected function getFields(){

		$fields = new FieldList(
			TextField::create('firstName'),
			TextField::create('lastName'),

			CreditCardField::create('number'),
			NumericField::create('expiryMonth'), //date field?
			NumericField::create('expiryYear'), //date field?
			NumericField::create('startMonth'), //date field?
			NumericField::create('startYear'), // date field?
			NumericField::create('cvv'),
			NumericField::create('issueNumber'),
			DropdownField::create('type'), //?what is this? credit card type...need to extract list from CreditCard

			TextField::create('billingAddress1'),
			TextField::create('billingAddress2'),
			TextField::create('billingCity'),
			TextField::create('billingPostcode'),
			TextField::create('billingState'),
			TextField::create('billingCountry'),
			PhoneNumberField::create('billingPhone'),

			TextField::create('shippingAddress1'),
			TextField::create('shippingAddress2'),
			TextField::create('shippingCity'),
			TextField::create('shippingPostcode'),
			TextField::create('shippingState'),
			TextField::create('shippingCountry'),
			PhoneNumberField::create('shippingPhone'),

			TextField::create('company'),
			EmailField::create('email')
		);

		return $fields;
	}

	function dummyData(){
		$this->loadDataFrom(array(
			'firstName' => 'jeremy',
			'lastName' => 'shipman',
			//'number' => '4111111111111111', //decline number
			'number' => '4242424242424242', //success number
			'expiryMonth' => '05',
			'expiryYear' => '14',
			'startMonth' => '05',
			'startYear' => '13',
			'cvv' => '560',
			'issueNumber' => '1',
			'type' => 'VISA',
			'billingAddress1' => '222 Almond Cresent',
			'billingAddress2' => 'Flat 123',
			'billingCity' => 'Takanini',
			'billingPostcode' => '1234',
			'billingState' => 'Auckland',
			'billingCountry' => 'New Zealand',
			'billingPhone' => '1234456789',
			'shippingAddress1' => '222 Almond Cresent',
			'shippingAddress2' => 'Flat 123',
			'shippingCity' => 'Takanini',
			'shippingPostcode' => '1234',
			'shippingState' => 'Auckland',
			'shippingCountry' => 'New Zealand',
			'shippingPhone' => '1234456789',
			'company' => 'Acme Corp',
			'email' => 'jeremy@acme.co'

		));
	}

}