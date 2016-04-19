<?php

use SilverStripe\Omnipay\GatewayInfo;
use Omnipay\Common\CreditCard;

/**
 * Helper for generating gateway fields, based on best practices.
 *
 * @package payment
 */
class GatewayFieldsFactory{

	protected $fieldgroups = array(
		'Card',
		'Billing',
		'Shipping',
		'Company',
		'Email'
	);

	protected $gateway;
	protected $groupdatefields = true;

	public function __construct($gateway = null, $fieldgroups = null) {
		$this->gateway = $gateway;
		$this->setFieldGroups($fieldgroups);
	}

	public function setFieldGroups($groups) {
		if (is_array($groups)) {
			$this->fieldgroups = $groups;
		}

		return $this;
	}

	public function setGateway($gateway) {
		$this->gateway = $gateway;

		return $this;
	}

	public function getFields() {
		$fields = new FieldList();
		foreach ($this->fieldgroups as $group) {
			if (method_exists($this, "get".$group."Fields")) {
				$fields->merge($this->{"get".$group."Fields"}());
			}
		}

		return $fields;
	}

	public function getCardFields() {
		$months = array();
		//generate list of months
		for ($x = 1; $x <= 12; $x++) {
			$months[$x] = date('m - F', mktime(0, 0, 0, $x, 1));
		}
		$year = date("Y");
		$range = 5;
		$startrange = range(date("Y", strtotime("-$range years")), $year);
		$expiryrange = range($year, date("Y", strtotime("+$range years")));

		$fields = array(
			"type" => DropdownField::create('type', _t("PaymentForm.TYPE", "Type"), $this->getCardTypes()),
			"name" => TextField::create('name', _t("PaymentForm.NAME", "Name on Card")),
			"number" => TextField::create('number', _t("PaymentForm.NUMBER", "Card Number"))
							->setDescription(_t("PaymentForm.NUMBERDESCRIPTION", "no dashes or spaces")),
			"startMonth" => DropdownField::create('startMonth', _t("PaymentForm.STARTMONTH", "Month"), $months),
			"startYear" => DropdownField::create('startYear', _t("PaymentForm.STARTYEAR", "Year"),
								array_combine($startrange, $startrange), $year
							),
			"expiryMonth" => DropdownField::create('expiryMonth', _t("PaymentForm.EXPIRYMONTH", "Month"), $months),
			"expiryYear" => DropdownField::create('expiryYear', _t("PaymentForm.EXPIRYYEAR", "Year"),
								array_combine($expiryrange, $expiryrange), $year
							),
			"cvv" => TextField::create('cvv', _t("PaymentForm.CVV", "Security Code"))
							->setMaxLength(5),
			"issueNumber" => TextField::create('issueNumber', _t("PaymentForm.ISSUENUMBER", "Issue Number"))
		);

		$this->cullForGateway($fields);
		//optionally group date fields
		if ($this->groupdatefields) {
			if (isset($fields['startMonth']) && isset($fields['startYear'])) {
				$fields['startMonth'] = new FieldGroup(_t("PaymentForm.START", "Start"),
					$fields['startMonth'], $fields['startYear']
				);
				$fields['startMonth']->addExtraClass('card_startyear');
				unset($fields['startYear']);
			}
			if (isset($fields['expiryMonth']) && isset($fields['expiryYear'])) {
				$fields['expiryMonth'] = new FieldGroup(_t("PaymentForm.EXPIRY", "Expiry"),
					$fields['expiryMonth'], $fields['expiryYear']
				);
				$fields['expiryMonth']->addExtraClass('card_expiry');
				unset($fields['expiryYear']);
			}
		}

		return FieldList::create($fields);
	}

	public function getCardTypes() {
		$card = new CreditCard();
		$brands = $card->getSupportedBrands();
		foreach ($brands as $brand => $x) {
			$brands[$brand] = _t("PaymentForm.".strtoupper($brand), $brand);
		}

		return $brands;
	}

	public function getBillingFields() {
		$fields = array(
			"billingAddress1" => TextField::create('billingAddress1', _t("PaymentForm.BILLINGADDRESS1", "")),
			"billingAddress2" => TextField::create('billingAddress2', _t("PaymentForm.BILLINGADDRESS2", "")),
			"city" => TextField::create('billingCity', _t("PaymentForm.BILLINGCITY", "City")),
			"postcode" => TextField::create('billingPostcode', _t("PaymentForm.BILLINGPOSTCODE", "Postcode")),
			"state" => TextField::create('billingState', _t("PaymentForm.BILLINGSTATE", "State")),
			"country" => TextField::create('billingCountry', _t("PaymentForm.BILLINGCOUNTRY", "Country")),
			"phone" => PhoneNumberField::create('billingPhone', _t("PaymentForm.BILLINGPHONE", "Phone"))
		);
		$this->cullForGateway($fields);

		return FieldList::create($fields);
	}

	public function getShippingFields() {
		$fields = array(
			"shippingAddress1" => TextField::create(
				'shippingAddress1', _t("PaymentForm.SHIPPINGADDRESS1", "Shipping Address")
			),
			"shippingAddress2" => TextField::create(
				'shippingAddress2', _t("PaymentForm.SHIPPINGADDRESS2", "Shipping Address 2")
			),
			"city" => TextField::create('shippingCity', _t("PaymentForm.SHIPPINGCITY", "Shipping City")),
			"postcode" => TextField::create('shippingPostcode', _t("PaymentForm.SHIPPINGPOSTCODE", "Shipping Postcode")),
			"state" => TextField::create('shippingState', _t("PaymentForm.SHIPPINGSTATE", "Shipping State")),
			"country" => TextField::create('shippingCountry', _t("PaymentForm.SHIPPINGCOUNTRY", "Shipping Country")),
			"phone" => PhoneNumberField::create('shippingPhone', _t("PaymentForm.SHIPPINGPHONE", "Shipping Phone"))
		);
		$this->cullForGateway($fields);

		return FieldList::create($fields);
	}

	public function getEmailFields() {
		$fields = array(
			"email" => EmailField::create('email', _t("PaymentForm.EMAIL", "Email"))
		);
		$this->cullForGateway($fields);

		return FieldList::create($fields);
	}

	public function getCompanyFields() {
		$fields = array(
			"company" => TextField::create('company', _t("PaymentForm.COMPANY", "Company"))
		);
		$this->cullForGateway($fields);

		return FieldList::create($fields);
	}

	protected function cullForGateway(&$fields, $defaults = array()) {
		$selected = array_merge($defaults, GatewayInfo::requiredFields($this->gateway));
		foreach ($fields as $name => $field) {
			if (!in_array($name, $selected)) {
				unset($fields[$name]);
			}
		}
	}

}
