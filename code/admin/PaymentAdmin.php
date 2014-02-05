<?php

/**
 * Model admin administration of payments.
 *
 * @package payment
 */
class PaymentAdmin extends ModelAdmin{

	private static $menu_title = "Payments";
	private static $url_segment = "payments";
	private static $menu_icon = 'omnipay/images/payment-admin.png';

	public $showImportForm = false;

	private static $managed_models = array(
		'Payment'
	);

	public function alternateAccessCheck(){
		return !$this->config()->hidden;
	}

}
