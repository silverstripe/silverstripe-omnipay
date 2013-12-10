<?php

/**
 * Product a for handling payment data
 *
 * @package payment
 */
class PaymentForm extends Form{

	protected $fieldgroups = array(
		'Card',
		'Name',
		'Billing',
		'Shipping',
		'Company',
		'Email'
	);

	function __construct($controller, $name = "PaymentForm", $fields = null, $actions, $validator = null, $fieldgroups = null){
		if(is_array($fieldgroups)){
			$this->fieldgroups = $fieldgroups;
		}
		$newfields = $this->getFields();
		if($fields){
			$newfields->merge($fields);
		}
		parent::__construct($controller, $name, $newfields, $actions, $validator);
	}

	protected function getFields(){
		$fields = new FieldList();
		foreach($this->fieldgroups as $group){
			if(method_exists($this, "get".$group."Fields")){
				$fields->merge($this->{"get".$group."Fields"}());
			}
		}
		
		return $fields;
	}

	function getNameFields(){
		return FieldList::create(
			TextField::create('name',_t("PaymentForm.NAME","Name on Card"))
		);
	}

	function getCardFields(){
		$months = array();
		for($x = 1; $x <= 12; $x++) {
			$months[] = date('m - F', mktime(0, 0, 0, $x, 1));
		}
		$year = date("Y");
		$range = 5;

		return FieldList::create(
			TextField::create('number',_t("PaymentForm.NUMBER","Card Number"))
				->setDescription(_t("PaymentForm.NUMBERDESCRIPTION","no dashes or spaces")),
			FieldGroup::create(_t("PaymentForm.STARTDATE","Start Date"),
				DropdownField::create('startMonth',_t("PaymentForm.STARTMONTH","Month"), $months),
				DropdownField::create('startYear',_t("PaymentForm.STARTYEAR","Year"), 
					range(date("Y",strtotime("-$range years")), $year), 
				$year)
			),
			FieldGroup::create(_t("PaymentForm.EXPIRYDATE","Expiry Date"),
				DropdownField::create('expiryMonth',_t("PaymentForm.EXPIRYMONTH","Month"), $months),
				DropdownField::create('expiryYear',_t("PaymentForm.EXPIRYYEAR","Year"),
					range($year, date("Y",strtotime("+$range years"))),
				$year)
			),
			TextField::create('cvv',_t("PaymentForm.CVV","Security Code")),
			TextField::create('issueNumber',_t("PaymentForm.ISSUENUMBER","Issue Number")),
			DropdownField::create('type',_t("PaymentForm.TYPE","Type"),$this->getCardTypes())
		);
	}

	function getBillingFields(){
		return FieldList::create(
			TextField::create('billingAddress1',_t("PaymentForm.BILLINGADDRESS1","Address")),
			TextField::create('billingAddress2',_t("PaymentForm.BILLINGADDRESS2","Address 2")),
			TextField::create('billingCity',_t("PaymentForm.BILLINGCITY","City")),
			TextField::create('billingPostcode',_t("PaymentForm.BILLINGPOSTCODE","Postcode")),
			TextField::create('billingState',_t("PaymentForm.BILLINGSTATE","State")),
			TextField::create('billingCountry',_t("PaymentForm.BILLINGCOUNTRY","Country")),
			PhoneNumberField::create('billingPhone',_t("PaymentForm.BILLINGPHONE","Phone"))
		);
	}

	function getShippingFields(){
		return FieldList::create(
			TextField::create('shippingAddress1',_t("PaymentForm.SHIPPINGADDRESS1","Shipping Address")),
			TextField::create('shippingAddress2',_t("PaymentForm.SHIPPINGADDRESS2","Shipping Address 2")),
			TextField::create('shippingCity',_t("PaymentForm.SHIPPINGCITY","Shipping City")),
			TextField::create('shippingPostcode',_t("PaymentForm.SHIPPINGPOSTCODE","Shipping Postcode")),
			TextField::create('shippingState',_t("PaymentForm.SHIPPINGSTATE","Shipping State")),
			TextField::create('shippingCountry',_t("PaymentForm.SHIPPINGCOUNTRY","Shipping Country")),
			PhoneNumberField::create('shippingPhone',_t("PaymentForm.SHIPPINGPHONE","Shipping Phone"))
		);
	}

	function getEmailFields(){
		return FieldList::create(
			EmailField::create('email',_t("PaymentForm.EMAIL","Email"))
		);
	}

	function getCompanyFields(){
		return FieldList::create(
			TextField::create('company',_t("PaymentForm.COMPANY","Company"))
		);
	}

	function getCardTypes(){
		$card = new Omnipay\Common\CreditCard();
		$brands = $card->getSupportedBrands();
		foreach($brands as $brand => $x){
			$brands[$brand] = _t("PaymentForm.".strtoupper($brand),$brand);
		}

		return $brands;
	}

}