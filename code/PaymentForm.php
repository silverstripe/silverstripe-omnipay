<?php

class PaymentForm extends Form{


	function __construct($controller, $name = "PaymentForm", $fields, $actions){

		$newfields = $this->getFields();
		$newfields->merge($fields);
		//TODO: validator
		
		parent::__construct($controller, $name, $newfields, $actions);

	}

	protected function getFields(){

		return new FieldList(
			TextField::create('firstName'),
			TextField::create('lastName'),

			CreditCardField::create('number'),
			NumericField::create('expiryMonth'), //date field?
			NumericField::create('expiryYear'), //date field?
			NumericField::create('startMonth'), //date field?
			NumericField::create('startYear'), // date field?
			NumericField::create('cvv'),
			NumericField::create('issueNumber'),
			DropdownField::create('type'), //?what is this?

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

	}

}