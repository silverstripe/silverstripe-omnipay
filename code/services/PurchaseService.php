<?php

use Omnipay\Common\CreditCard;

class PurchaseService extends PaymentService{

	/**
	 * Attempt to make a payment.
	 * 
	 * @param  array $data returnUrl/cancelUrl + customer creditcard and billing/shipping details.
	 * 	Some keys (e.g. "amount") are overwritten with data from the associated {@link $payment}.
	 *  If this array is constructed from user data (e.g. a form submission), please take care
	 *  to whitelist accepted fields, in order to ensure sensitive gateway parameters like "freeShipping" can't be set.
	 *  If using {@link Form->getData()}, only fields which exist in the form are returned,
	 *  effectively whitelisting against arbitrary user input.
	 * @return ResponseInterface omnipay's response class, specific to the chosen gateway.
	 */
	public function purchase($data = array()) {
		if ($this->payment->Status !== "Created") {
			return null; //could be handled better? send payment response?
		}
		if (!$this->payment->isInDB()) {
			$this->payment->write();
		}
		//update success/fail urls
		$this->update($data);

		//set the client IP address, if not already set
		if(!isset($data['clientIp'])){
			$data['clientIp'] = Controller::curr()->getRequest()->getIP();
		}

		$gatewaydata = array_merge($data,array(
			'card' => $this->getCreditCard($data),
			'amount' => (float) $this->payment->MoneyAmount,
			'currency' => $this->payment->MoneyCurrency,
			//set all gateway return/cancel/notify urls to PaymentGatewayController endpoint
			'returnUrl' => $this->getEndpointURL("complete", $this->payment->Identifier),
			'cancelUrl' => $this->getEndpointURL("cancel", $this->payment->Identifier),
			'notifyUrl' => $this->getEndpointURL("notify", $this->payment->Identifier)
		));
		
		if(!isset($gatewaydata['transactionId'])){
			$gatewaydata['transactionId'] = $this->payment->Identifier;
		}

		$request = $this->oGateway()->purchase($gatewaydata);

		$message = $this->createMessage('PurchaseRequest', $request);
		$message->SuccessURL = $this->returnurl;
		$message->FailureURL = $this->cancelurl;
		$message->write();

		$gatewayresponse = $this->createGatewayResponse();
		try {
			$response = $this->response = $request->send();
			$gatewayresponse->setOmnipayResponse($response);
			//update payment model
			if (GatewayInfo::is_manual($this->payment->Gateway)) {
				//initiate manual payment
				$this->createMessage('AuthorizedResponse', $response);
				$this->payment->Status = 'Authorized';
				$this->payment->write();
				$gatewayresponse->setMessage("Manual payment authorised");
			} elseif ($response->isSuccessful()) {
				//successful payment
				$this->createMessage('PurchasedResponse', $response);
				$this->payment->Status = 'Captured';
				$gatewayresponse->setMessage("Payment successful");
				$this->payment->write();
				$this->payment->extend('onCaptured', $gatewayresponse);
			} elseif ($response->isRedirect()) {
				// redirect to off-site payment gateway
				$this->createMessage('PurchaseRedirectResponse', $response);
				$this->payment->Status = 'Authorized';
				$this->payment->write();
				$gatewayresponse->setMessage("Redirecting to gateway");
			} else {
				//handle error
				$this->createMessage('PurchaseError', $response);
				$gatewayresponse->setMessage(
					"Error (".$response->getCode()."): ".$response->getMessage()
				);
			}
		} catch (Omnipay\Common\Exception\OmnipayException $e) {
			$this->createMessage('PurchaseError', $e);
			$gatewayresponse->setMessage($e->getMessage());
		}
		$gatewayresponse->setRedirectURL($this->getRedirectURL());

		return $gatewayresponse;
	}

	/**
	 * Finalise this payment, after off-site external processing.
	 * This is ususally only called by PaymentGatewayController.
	 * @return PaymentResponse encapsulated response info
	 */
	public function completePurchase($data = array()) {
		$gatewayresponse = $this->createGatewayResponse();

		//set the client IP address, if not already set
		if(!isset($data['clientIp'])){
			$data['clientIp'] = Controller::curr()->getRequest()->getIP();
		}

		$gatewaydata = array_merge($data, array(
			'amount' => (float) $this->payment->MoneyAmount,
			'currency' => $this->payment->MoneyCurrency
		));

		$this->payment->extend('onBeforeCompletePurchase', $gatewaydata);

		$request = $this->oGateway()->completePurchase($gatewaydata);
		$this->createMessage('CompletePurchaseRequest', $request);
		$response = null;
		try {
			$response = $this->response = $request->send();
			$gatewayresponse->setOmnipayResponse($response);
			if ($response->isSuccessful()) {
				$this->createMessage('PurchasedResponse', $response);
				$this->payment->Status = 'Captured';
				$this->payment->write();
				$this->payment->extend('onCaptured', $gatewayresponse);
			} else {
				$this->createMessage('CompletePurchaseError', $response);
			}
		} catch (Omnipay\Common\Exception\OmnipayException $e) {
			$this->createMessage("CompletePurchaseError", $e);
		}

		return $gatewayresponse;
	}

	public function cancelPurchase() {
		//TODO: do lookup? / try to complete purchase?
		//TODO: omnipay void call
		$this->payment->Status = 'Void';
		$this->payment->write();
		$this->createMessage('VoidRequest', array(
			"Message" => "The payment was cancelled."
		));

		//return response
	}

	/**
	 * @return \Omnipay\Common\CreditCard
	 */
	protected function getCreditCard($data) {
		return new CreditCard($data);
	}

}
