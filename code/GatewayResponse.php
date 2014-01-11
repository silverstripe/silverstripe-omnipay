<?php

/**
 * Wrapper for omnipay responses, which allow us to customise functionality
 *
 * @package payment
 */
class GatewayResponse{

	private $response, $payment, $message;

	public function __construct(Payment $payment){
		$this->payment = $payment;
	}

	public function setOmnipayResponse(Omnipay\Common\Message\AbstractResponse $response){
		$this->response = $response;

		return $this;
	}

	public function getOmnipayResponse(){
		return $this->response;
	}

	public function setMessage($message){
		$this->message = $message;

		return $this;
	}

	public function getMessage(){
		return $this->message;
	}

	/**
	 * Check if the response indicates a successful gateway action
	 * @return boolean
	 */
	public function isSuccessful(){
		return $this->response && $this->response->isSuccessful();
	}

	/**
	 * Check if a redirect is required
	 * @return boolean
	 */
	public function isRedirect(){
		return $this->response && $this->response->isRedirect();
	}

	/**
	 * Get the appropriate redirect url
	 */
	public function redirectURL(){
		$url = null;
		if ($this->isSuccessful()) {
			$url = $this->payment->getReturnUrl();
		} elseif ($this->isRedirect()) {
			$url = $this->response->getRedirectUrl();
		} else {
			$url = $this->payment->getCancelUrl();
		}
		return $url;
	}

	/**
	 * Do a redirect, using the current controller
	 */
	public function redirect(){
		Controller::curr()->redirect($this->redirectURL());
	}

}


