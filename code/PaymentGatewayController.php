<?php

/**
 * Payment Gateway Controller
 *
 * This controller handles redirects from gateway servers, and also behind-the-scenes
 * requests that gateway servers to notify our application of successful payment.
 *
 * @package payment
 */
class PaymentGatewayController extends Controller{

	private static $allowed_actions = array(
		'endpoint'
	);

	/**
	 * Generate an absolute url for gateways to return to, or send requests to.
	 * @param  GatewayMessage $message message that redirect applies to.
	 * @param  string             $action      the intended action of the gateway
	 * @param  string             $returnurl   the application url to re-redirect to
	 * @return string                          the resulting redirect url
	 */
	public static function get_endpoint_url($action, $identifier) {
		return Director::absoluteURL(
			Controller::join_links('paymentendpoint', $identifier, $action)
		);
	}

	/**
	 * The main action for handling all requests.
	 * It will redirect back to the application in all cases,
	 * but will not update the Payment/Transaction models if they are not found,
	 * or allowed to be updated.
	 */
	public function index() {		
		$payment = $this->getPayment();
		if (!$payment) {
			return $this->httpError(404, _t("Payment.NOTFOUND", "Payment could not be found."));
		}

		//isolate the gateway request message containing success / failure urls
		$message = $payment->Messages()
			->filter("ClassName", array("PurchaseRequest","AuthorizeRequest"))
			->first();

		$service = PurchaseService::create($payment);
		
		//redirect if payment is already a success
		if ($payment->isComplete()) {
			return $this->redirect($this->getSuccessUrl($message));
		}

		//do the payment update
		$response = null;
		switch ($this->request->param('Status')) {
			case "complete":
				$serviceResponse = $service->completePurchase(array(
					'clientIp' => $this->request->getIP()
				));
				if($serviceResponse->isSuccessful()){
					$response = $this->redirect($this->getSuccessUrl($message));
				} else {
					$response = $this->redirect($this->getFailureUrl($message));
				}
				break;
			case "notify":
				$serviceResponse = $service->completePurchase(array(
					'clientIp' => $this->request->getIP()
				));
				// Allow implementations where no redirect happens,
				// since gateway failsafe callbacks might expect a 2xx HTTP response
				$response = new SS_HTTPResponse('', 200);
				break;
			case "cancel":
				//TODO: store cancellation message / void payment
				$response = $this->redirect($this->getFailureUrl($message));
				break;
			default:
				$response = $this->httpError(404, _t("Payment.INVALIDURL", "Invalid payment url."));
		}

		return $response;
	}

	/**
	 * Get the the payment according to the identifer given in the url
	 * @return Payament the payment
	 */
	private function getPayment() {
		return Payment::get()
				->filter('Identifier', $this->request->param('Identifier'))
				->filter('Identifier:not', "")
				->first();
	}

	/**
	 * Get the success url to redirect to.
	 * If a url hasn't been stored, then redirect to base url.
	 * @return string the url
	 */
	private function getSuccessUrl($message) {
		return $message->SuccessURL ? $message->SuccessURL : Director::baseURL();
	}

	/**
	 * Get the failure url to redirect to.
	 * If a url hasn't been stored, then redirect to base url.
	 * @return string the url
	 */
	private function getFailureUrl($message) {
		return $message->FailureURL ? $message->FailureURL : Director::baseURL();
	}

}
