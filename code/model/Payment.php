<?php

/**
 * Payment DataObject
 *
 * This class is used for storing a payment amount, and it's status of being
 * paid or not, and the gateway used to make payment.
 *
 * @package payment
 */
final class Payment extends DataObject{

	private static $db = array(
		'Gateway' => 'Varchar(50)', //this is the omnipay 'short name'
		'Money' => 'Money', //contains Amount and Currency
		'Status' => "Enum('Created,Authorized,Captured,Refunded,Void','Created')"
	);

	private static $has_many = array(
		'Messages' => 'PaymentMessage'
	);

	private static $defaults = array(
		'Status' => 'Created'
	);

	private static $casting = array(
		"Amount" => "Decimal"
	);

	private static $summary_fields = array(
		'Money' => 'Money',
		'GatewayTitle' => 'Gateway',
		'Status' => 'Status',
		'Created.Nice' => 'Created'
	);

	private static $default_sort = "\"Created\" DESC, \"ID\" DESC";

	public function getCMSFields() {
		$fields = new FieldList(
			TextField::create("MoneyValue", _t("Payment.MONEY", "Money"), $this->dbObject('Money')->Nice()),
			TextField::create("GatewayTitle", _t("Payment.GATEWAY", "Gateway"))
		);
		$fields = $fields->makeReadonly();
		$fields->push(
			GridField::create("Messages", _t("Payment.MESSAGES", "Messages"), $this->Messages(),
				GridFieldConfig_RecordViewer::create()
			)
		);

		$this->extend('updateCMSFields', $fields);

		return $fields;
	}

	/**
	 * Change search context to use a dropdown for list of gateways.
	 */
	public function getDefaultSearchContext() {
		$context = parent::getDefaultSearchContext();
		$fields = $context->getSearchFields();
		$fields->removeByName('Gateway');
		$fields->insertBefore(DropdownField::create('Gateway', 'Gateway',
			GatewayInfo::get_supported_gateways()
		)->setHasEmptyDefault(true), 'Status');
		$fields->fieldByName('Status')->setHasEmptyDefault(true);

		return $context;
	}

	/**
	 * Set gateway, amount, and currency in one function.
	 * @param  string $gateway   omnipay gateway short name
	 * @param  float $amount     monetary amount
	 * @param  string $currency the currency to set
	 * @return  Payment this object for chaining
	 */
	public function init($gateway, $amount, $currency) {
		$this->setGateway($gateway);
		$this->setAmount($amount);
		$this->setCurrency($currency);
		return $this;
	}

	public function getTitle() {
		return implode(' ', array(
			$this->getGatewayTitle(),
			$this->forTemplate()->Nice(),
			$this->dbObject('Created')->Date()
		));
	}

	/**
	 * Set the payment gateway
	 * @param string $gateway the omnipay gateway short name.
	 * @return Payment this object for chaining
	 */
	public function setGateway($gateway) {
		if ($this->Status == 'Created') {
			$this->setField('Gateway', $gateway);
		}
		return $this;
	}

	public function getGatewayTitle() {
		return GatewayInfo::nice_title($this->Gateway);
	}

	/**
	 * Get the payment amount
	 * @return string amount of this payment
	 */
	public function getAmount() {
		return $this->MoneyAmount;
	}

	/**
	 * Set the payment amount, but only when the status is 'Created'.
	 * @param float $amt value to set the payment to
	 * @return  Payment this object for chaining
	 */
	public function setAmount($amount) {
		if ($amount instanceof Money) {
			$this->setField("Money", $amount);
		} elseif ($this->Status == 'Created' && is_numeric($amount)) {
			$this->MoneyAmount = $amount;
		}
		return $this;
	}

	/**
	 * Get just the currency of this payment's money component
	 * @return string the currency of this payment
	 */
	public function getCurrency() {
		return $this->MoneyCurrency;
	}

	/**
	 * Set the payment currency, but only when the status is 'Created'.
	 * @param string $currency the currency to set
	 */
	public function setCurrency($currency) {
		if ($this->Status == 'Created') {
			$this->MoneyCurrency = $currency;
		}

		return $this;
	}

	/**
	 * This payment requires no more processing.
	 * @return boolean completion
	 */
	public function isComplete() {
		return $this->Status == 'Captured' ||
				$this->Status == 'Refunded' ||
				$this->Status == 'Void';
	}

	public function forTemplate() {
		return $this->dbObject('Money');
	}

}
