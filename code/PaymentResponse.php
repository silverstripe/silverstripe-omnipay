<?php

/**
 * Wrapper for omnipay responses, which allow us to customise functionality
 */
class PaymentResponse{

	private $response, $payment;

	public function __construct(Omnipay\Common\Message\AbstractResponse $response, Payment $payment){
		$this->response = $response;
		$this->payment = $payment;
	}

	function isSuccessful(){
		return $this->response->isSuccessful();
	}

	function isRedirect(){
		return $this->response->isRedirect();
	}

	function redirect(){
		$url = null;
		if ($this->isSuccessful()) {
			$url = $payment->getReturnUrl();
		} elseif ($this->isRedirect()) {
			$url = $this->response->getRedirectUrl();
		} else {
			//TODO: should this instead be the current url?
			$url = $payment->getCancelUrl();
		}
		Controller::curr()->redirect($url);
	}

}
