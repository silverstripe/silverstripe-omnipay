<?php

class PaymentController extends Controller{
	
	private static $allowed_actions = array(
		'endpoint'
	);

	public function endpoint(){

		$transaction = PaymentTransaction::get()->filter('Reference', $this->request->param('Reference'));

		//update the payment transaction


		//redirect back to application
		//$this->redirect($redirecturl);
	}

}