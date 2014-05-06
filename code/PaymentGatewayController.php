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
	 * @param  string             $status      the intended status / action of the gateway
	 * @param  string             $returnurl   the application url to re-redirect to
	 * @return string                          the resulting redirect url
	 */
	public static function get_return_url(GatewayMessage $message, $status = 'complete') {
		return Director::absoluteURL(
			Controller::join_links(
				'paymentendpoint', //as defined in _config/routes.yml
				$message->Identifier,
				$status
			)
		);
	}

	/**
	 * The main action for handling all requests.
	 * It will redirect back to the application in all cases,
	 * but will not update the Payment/Transaction models if they are not found,
	 * or allowed to be updated.
	 */
	public function index() {
		$message = $this->getRequestMessage();
		if (!$message || !$message->Payment()->exists()) {
			return $this->httpError(404, _t("Payment.NOTFOUND", "Payment could not be found."));
		}
		$payment = $message->Payment();
		$service = PurchaseService::create($payment);
		
		//redirect if payment is already a success
		if ($payment->isComplete()) {
			return $this->redirect($this->getSuccessUrl($message));
		}

		//do the payment update
		$response = null;
		switch ($this->request->param('Status')) {
			case "complete":
				$serviceResponse = $service->completePurchase();
				if($serviceResponse->isSuccessful()){
					$response = $this->redirect($this->getSuccessUrl($message));
				} else {
					$response = $this->redirect($this->getFailureUrl($message));
				}
				break;
			case "notify":
				$serviceResponse = $service->completePurchase();
				// Allow implementations where no redirect happens,
				// since gateway failsafe callbacks might expect a 2xx HTTP response
				$response = new SS_HTTPResponse('', 200);
				break;
			case "cancel":
				//TODO: store cancellation message
				$response = $this->redirect($this->getFailureUrl($message));
				break;
			default:
				$response = $this->httpError(404, _t("Payment.INVALIDURL", "Invalid payment url."));
		}

		return $response;
	}

	/**
	 * Get the message storing the identifier for this payment
	 * @return GatewayMessage the transaction
	 */
	private function getRequestMessage() {
		return GatewayMessage::get()
				->filter('Identifier', $this->request->param('Identifier'))
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
