<?php

/**
 * Payment Gateway Controller
 *
 * This controller handles redirects from gateway servers, and also behind-the-scenes
 * requests that gateway servers to notify our application of successful payment.
 * 
 * @package payment
 */
final class PaymentGatewayController extends Controller{
	
	private static $allowed_actions = array(
		'endpoint'
	);

	/**
	 * Generate an absolute url for gateways to return to, or send requests to.
	 * @param  PaymentTransaction $transaction transaction that redirect applies to.
	 * @param  string             $status      the intended status / action of the gateway
	 * @param  string             $returnurl   the application url to re-redirect to
	 * @return string                          the resulting redirect url
	 */
	public static function get_return_url(GatewayTransaction $transaction, $status = 'complete', $returnurl = null){
		return Director::absoluteURL(
			Controller::join_links(
				'paymentendpoint', //as defined in _config/routes.yml
				$transaction->Identifier,
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
		$transaction = $this->getTransaction();
		if(!$transaction){			
			//TODO: log failure && store a message for user?
			return $this->redirect($this->getRedirectUrl());
		}
		$payment = $transaction->Payment();

		//check if payment is already a success
		if(!$payment || $payment->isComplete()){
			return $this->redirect($this->getRedirectUrl());
		}
		//store redirect url in payment model
		$payment->setReturnUrl($this->getRedirectUrl());

		//do the payment update
		switch($this->param('Status')){
			case "complete":
				$response = $payment->completePurchase();
				break;
			case "cancel":
				//mark as cancelled?...or failure? void?
				
				break;
		}
		
		return $payment->redirect(); //redirect back to application
	}

	/**
	 * Get the transaction by the given identifier
	 * @return PaymentTransaction the transaction
	 */
	private function getTransaction(){
		return GatewayTransaction::get()
						->filter('Identifier',$this->request->param('Identifier'))
						->First();
	}

	/**
	 * Get the url to redirect to.
	 * If a url hasn't been stored in the url, then redirect to base url.
	 * @return string the url
	 */
	private function getRedirectUrl(){
		//TODO: introduce callback / extension hook to allow developers to update return url???
		$url = $this->request->param('ReturnURL');
		if($url){
			return base64_decode(urldecode($url));
		}
		return Director::baseURL();
	}

}
