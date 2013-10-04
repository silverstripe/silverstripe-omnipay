<?php

/**
 * PaymentController
 *
 * This controller handles redirects from gateway servers, and also behind-the-scenes
 * requests that gateway servers to notify our application of successful payment.
 */
class PaymentController extends Controller{
	
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
	public static function get_return_url(PaymentTransaction $transaction, $status = 'complete', $returnurl = null){
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
	 * The main action for handling all requests
	 */
	public function index(){

		$transaction = PaymentTransaction::get()
			->filter('Identifier',$this->request->param('Identifier'))
			->First();
		if(!$transaction){
			//log failure
			//store message for user
			return $this->redirect($this->getRedirectUrl());
		}

		$payment = $transaction->Payment();

		//security checks - verify that payment is allowed to be updated
			//check token
			//call completePurchase, completeAuthorise omnipay functions

		switch($this->param('Status')){
			case "complete":

				//TODO: try/catch
				try {
					$payment->completePayment();
				} catch (\Exception $e) {
					
				}
				//update the payment transaction

				return;
			case "cancel":
				//mark as cancelled?...or failure?
				return;
		}

		//redirect back to application
		return $this->redirect($this->getRedirectUrl());
	}

	/**
	 * Get the url to redirect to.
	 * If a url hasn't been stored in the url, then redirect to base url.
	 * @return [type] [description]
	 */
	private function getRedirectUrl(){
		$url = $this->request->param('ReturnURL');
		if($url){
			return base64_decode(urldecode($url));
		}
		return Director::baseURL();
	}

}