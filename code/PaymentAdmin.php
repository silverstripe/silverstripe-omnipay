<?php

class PaymentAdmin extends ModelAdmin{
	
	private static $menu_title = "Payments";
	private static $url_segment = "payments";

	public $showImportForm = false;

	private static $managed_models = array(
		'Payment'
	);

}