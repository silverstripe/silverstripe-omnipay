<?php

class PaymentController extends Controller{
	
	private static $allowed_actions = array(
		'endpoint'
	);

	public static function get_url(){
		return 'PaymentController';
	}

	public function endpoint(){

		$transaction = PaymentTransaction::get()
			->filter('Reference', $this->request->param('Reference'));

		//update the payment transaction

		//redirect back to application
		//$this->redirect($redirecturl);
	}

	public static function get_return_url(PaymentTransaction $transaction, $action = null){
		return Director::absoluteURL(
			Controller::join_links(self::get_url(),'endpoint',$transaction->Reference,$action)
		);
	}

}