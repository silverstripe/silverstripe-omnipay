<?php

if(class_exists("Page_Controller")){
/**
 * Handles making payment(s) for a given DataObject that is extended with Payable.
 */
class PaymentController extends Page_Controller{

	private static $allowed_actions = array(
		'GatewaySelectForm',
		'GatewayDataForm'
	);

	protected $payable;
	protected $amount;
	protected $successurl;
	protected $cancelurl;
	protected $currency;

	public function __construct($controller, $name, DataObjectInterface $payable, $amount) {
		if(!is_numeric($amount)){
			throw new InvalidArgumentException("Amount must be numeric");
		}
		$record = new Page(array(
			'ID' => -1,
			'Title' => $controller->Title,
			'ParentID' => $controller->ID,
			'URLSegment' => $name
		));
		parent::__construct($record);
		$this->payable = $payable;
		$this->amount = $amount;
		//get currency from defaults
		$defaults = Payment::config()->defaults;
		if(isset($defaults['MoneyCurrency'])){
			$this->currency = $defaults['MoneyCurrency'];
		}
		$this->successurl = $controller->Link();
	}

	/**
	 * Set the url to visit after a payment completes.
	 * @param string $url
	 */
	public function setSuccessURL($url) {
		$this->successurl = $url;

		return $this;
	}

	/**
	 * Get the success url 
	 * @return string
	 */
	public function getSuccessURL() {
		return $this->successurl;
	}

	/**
	 * Set the url to visit after cancelling a payment.
	 * @param string $url
	 */
	public function setCancelURL($url) {
		$this->cancelurl = $url;

		return $this;
	}

	/**
	 * Get the cancel url
	 * @return string|null
	 */
	public function getCancelURL() {
		return $this->cancelurl;
	}

	/**
	 * Work out if the Payable has been paid for.
	 * @return boolean
	 */
	public function isPaid() {
		return ($this->payable->TotalPaid() >= $this->amount);
	}

	/**
	 * Get the object being paid for
	 * @return DataObjectInterface
	 */
	public function getPayable(){
		return $this->payable;
	}

	/**
	 * Get the amount
	 * @return float
	 */
	public function getAmount(){
		return $this->amount;
	}

	/**
	 * Set the currency for this payment
	 */
	public function setCurrency($currency) {
		$this->currency = $currency;

		return $this;
	}

	/**
	 * Get the currency of the payment
	 * @return string
	 */
	public function getCurrency() {
		return $this->currency;
	}

	/**
	 * Redirect to success url automatically if payment
	 * is already complete.
	 */
	public function init() {
		parent::init();
		//check if payment is even required
		if($this->isPaid()){
			return $this->redirect($this->successurl);
		}
	}

	/**
	 * Index action will display different forms,
	 * depending on whether a gateway has been selected.
	 */
	public function index() {
		$money = $this->getMoney();
		$newpayment = $this->getCurrentPayment();
		$form = '';
		if($newpayment){
			$form = $this->GatewayDataForm();
		}else{
			$form = $this->GatewaySelectForm();
		}

		$data = new ArrayData(array(
			'CurrentPayment' => $newpayment,
			'Payable' => $this->payable,
			'Amount' => $money
		));

		return array(
			'Content' => $data->renderWith("PaymentControllerContent"),
			'Form' => $form
		);
	}

	/**
	 * Get the most recently created payment that
	 * has not started interacting with its gateway.
	 * @return Payment|null
	 */
	public function getCurrentPayment() {
		return $this->payable->Payments()
						->filter("Status", "Created")
						->sort("Created", "DESC")
						->first();
	}

	/**
	 * Form for selecting a gateway.
	 */
	public function GatewaySelectForm() {
		$gateways = GatewayInfo::get_supported_gateways();
		$fields = new FieldList(
			new OptionsetField("Gateway", _t("PaymentController.METHOD", "Payment Method"), $gateways)
		);
		$validator = new RequiredFields('Gateway');
		$actions = new FieldList(
			new FormAction("select", _t("PaymentController.DIFFERENTMETHOD", "Make Payment"))
		);
		$form = new Form($this, "GatewaySelectForm", $fields, $actions, $validator);
		$this->extend('updateGatewaySelectForm', $form);

		return $form;
	}

	public function select($data, $form) {
		if(!GatewayInfo::is_supported($data['Gateway'])){
			$form->addErrorMessage("Gateway", _t("PaymentController.METHODNOTSUPPORTED", "Method is not supported"), "bad");
			return $this->redirectBack();
		}
		//create payment using gateway
		$payment = $this->createPayment($data['Gateway']); 

		//redirect to offsite gateway, if there are no fields to fill out
		return $this->redirectBack();
	}

	/**
	 * Helper for creating the Payment model.
	 * @param  string $gateway
	 * @return Payment
	 */
	protected function createPayment($gateway) {
		$payment = Payment::create()
					->init($gateway, $this->amount, $this->currency);
		$this->payable->Payments()->add($payment);

		return $payment;
	}

	/**
	 * Form for collecting gateway data.
	 */
	public function GatewayDataForm() {
		$payment = $this->getCurrentPayment();
		if(!$payment){
			//redirect if there is no payment object available
			return $this->redirect($this->Link());
		}
		$factory = new GatewayFieldsFactory($payment->Gateway);
		$fields = $factory->getFields();
		//TODO: never let CC details be stored in session (e.g. validation)
		//TODO: force requirement of SSL on live sites
		$actions = new FieldList(
			$cancelaction = new FormAction("cancel", _t("PaymentController.DIFFERENTMETHOD", "Choose Different Method")),
			$payaction = new FormAction("pay", _t("PaymentController.DIFFERENTMETHOD", "Make Payment"))
		);
		$cancelaction->setAttribute("formnovalidate", "formnovalidate");
		$validator = new RequiredFields(
			GatewayInfo::required_fields($payment->Gateway)
		);
		$form = new Form($this, "GatewayDataForm", $fields, $actions, $validator);
		$this->extend('updateGatewayDataForm', $form);
		//allow cancel action to run without validation
		if(!empty($_REQUEST['action_cancel'])) {
			$form->unsetValidator();
		}

		return $form;
	}

	/**
	 * Cancel the current payment.
	 */
	public function cancel($data, $form) {
		$payment = $this->getCurrentPayment();
		if($payment){
			$response = PurchaseService::create($payment)
				->cancelPurchase();
		}
		$url = $this->cancelurl ? $this->cancelurl : $this->Link();
		return $this->redirect($url);
	}

	/**
	 * Proceed to process payment using given data.
	 */
	public function pay($data, $form) {
		$payment = $this->getCurrentPayment();
		//data from form is safe
		$data = $form->getData();
		//TODO: pass in custom data
		return $this->processPayment($payment, $data);
	}

	/**
	 * Initiate the actual purchase, and do redirect to
	 * gateway site, success url or cancel url.
	 * @param  Payment $payment
	 * @param  array $data
	 */
	protected function processPayment(Payment $payment, $data) {
		$response = PurchaseService::create($payment)
					->setReturnUrl($this->successurl)
					->setCancelUrl($this->cancelurl)
					//manual payments need to become "Captured" to work with this controller
					->setManualPurchaseStatus("Captured")
					->purchase($data);

		return $response->redirect();
	}

	/**
	 * Package the amcount and currency into a Money object.
	 * @return Money
	 */
	protected function getMoney() {
		$money = new Money("Amount");
		$money->setAmount($this->amount);
		$money->setCurrency($this->currency);

		return $money;
	}

}
}
