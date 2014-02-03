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
	public static function get_return_url(GatewayMessage $message, $status = 'complete', $returnurl = null){
		return Director::absoluteURL(
			Controller::join_links(
				'paymentendpoint', //as defined in _config/routes.yml
				$message->Identifier,
				$status,
				urlencode(base64_encode($returnurl))
			)
		);
	}

	/**
	 * The main action for handling all requests.
	 * It will redirect back to the application in all cases,
	 * but will not update the Payment/Transaction models if they are not found,
	 * or allowed to be updated.
	 */
	public function index(){
		$message = $this->getRequestMessage();
		if(!$message){			
			//TODO: log failure && store a message for user?
			return $this->redirect($this->getRedirectUrl());
		}
		$payment = $message->Payment();
		$service = PurchaseService::create($payment);

		//check if payment is already a success
		if(!$payment || $payment->isComplete()){
			return $this->redirect($this->getRedirectUrl());
		}
		//store redirect url in payment model
		$service->setReturnUrl($this->getRedirectUrl());

		//do the payment update
		switch($this->request->param('Status')){
			case "complete":
				$response = $service->completePurchase();
				return $this->redirect($response->getRedirectURL());
			case "cancel":
				//$response = $payment->void();
				break;
		}
	}

	/**
	 * Get the message storing the identifier for this payment
	 * @return GatewayMessage the transaction
	 */
	private function getRequestMessage(){
		return GatewayMessage::get()
				->filter('Identifier', $this->request->param('Identifier'))
				->first();
	}

	/**
	 * Get the url to redirect to.
	 * If a url hasn't been stored in the url, then redirect to base url.
	 * @return string the url
	 */
	private function getRedirectUrl(){
		$url = $this->request->param('ReturnURL');
		if($url){
			return base64_decode(urldecode($url));
		}
		return Director::baseURL();
	}

}
