<?php

/**
 * Wrapper for omnipay responses, which allow us to customise functionality
 *
 * @package payment
 */
class GatewayResponse{

	/**
	 * @var Omnipay\Common\Message\AbstractResponse
	 */
	private $response;
	
	/**
	 * @var Payment
	 */
	private $payment;
	
	/**
	 * @var String Success message which can be exposed to the user
	 * if the payment was successful. Not persisted in database, so can't
	 * be used for offsite payment processing.
	 */
	private $message;
	
	/**
	 * @var String URL to an endpoint within SilverStripe that can process
	 * the response, usually {@link PaymentGatewayController}.
	 * This controller might further redirect the user, based on the
	 * $SuccessURL and $FailureURL messages in {@link GatewayRequestMessage}.
	 */
	private $redirect;

	public function __construct(Payment $payment) {
		$this->payment = $payment;
	}

	/**
	 * Check if the response indicates a successful gateway action
	 *
	 * @return boolean
	 */
	public function isSuccessful() {
		return $this->response && $this->response->isSuccessful();
	}

	/**
	 * Check if a redirect to an offsite gateway is required.
	 * Note that {@link redirect()} will still cause a redirect for onsite gateways,
	 * but in this case uses the provided {@link redirect} URL rather than asking the gateway
	 * on where to redirect.
	 * 
	 * @return boolean
	 */
	public function isRedirect() {
		return $this->response && $this->response->isRedirect();
	}

	/**
	 * @param Omnipay\Common\Message\AbstractResponse $response
	 */
	public function setOmnipayResponse(Omnipay\Common\Message\AbstractResponse $response) {
		$this->response = $response;

		return $this;
	}

	/**
	 * @return Omnipay\Common\Message\AbstractResponse
	 */
	public function getOmnipayResponse() {
		return $this->response;
	}

	/**
	 * See {@link $message}.
	 * 
	 * @param String $message
	 */
	public function setMessage($message) {
		$this->message = $message;

		return $this;
	}

	/**
	 * @return String
	 */
	public function getMessage() {
		return $this->message;
	}

	/**
	 * @return Payment
	 */
	public function getPayment() {
		return $this->payment;
	}

	/**
	 * See {@link $redirect}.
	 * 
	 * @param String $url
	 */
	public function setRedirectURL($url) {
		$this->redirect = $url;

		return $this;
	}

	/**
	 * Get the appropriate redirect url
	 */
	public function getRedirectURL() {
		return $this->redirect;
	}

	/**
	 * Do a straight redirect to the denoted {@link redirect} URL if the payment gateway is onsite.
	 * If the gateway is offsite, redirect the user to the gateway host instead.
	 * This redirect can take two forms: A straight URL with payment data transferred as GET parameters,
	 * or a self-submitting form with payment data transferred through POST.
	 *
	 * @return SS_HTTPResponse
	 */
	public function redirect() {
		if($this->response && $this->response->isRedirect()) {
			// Offsite gateway, use payment response to determine redirection,
			// either through GET with simep URL, or POST with a self-submitting form.
			$redirectOmnipayResponse = $this->response->getRedirectResponse();
			if($redirectOmnipayResponse instanceof Symfony\Component\HttpFoundation\RedirectResponse) {
				return Controller::curr()->redirect($redirectOmnipayResponse->getTargetUrl());	
			} else {
				return new SS_HTTPResponse((string)$redirectOmnipayResponse->getContent(), 200);
			}		
		} else {
			// Onsite gateway, redirect to application specific "completed" URL
			return Controller::curr()->redirect($this->getRedirectURL());
		}
		
	}

}
