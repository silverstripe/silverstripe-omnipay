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
		$message = $this->createMessage('PurchaseRequest');
		$message->SuccessURL = isset($data['returnUrl']) ?
							$data['returnUrl'] :
							$this->returnurl;
		$message->FailureURL = isset($data['cancelUrl']) ?
							$data['cancelUrl'] :
							$this->cancelurl;
		$message->write();
		$request = $this->oGateway()->purchase(array_merge(
			$data,
			array(
				'card' => $this->getCreditCard($data),
				'amount' => (float) $this->payment->MoneyAmount,
				'currency' => $this->payment->MoneyCurrency,
				'transactionId' => $message->Identifier,
				'clientIp' => isset($data['clientIp']) ? $data['clientIp'] : null,
				'returnUrl' => PaymentGatewayController::get_return_url($message, 'complete'),
				'notifyUrl' => PaymentGatewayController::get_return_url($message, 'notify'),
				'cancelUrl' => PaymentGatewayController::get_return_url($message, 'cancel')
			)
		));
		$this->logToFile($request->getParameters(), "PurchaseRequest_post");
		$gatewayresponse = $this->createGatewayResponse();
		try {
			$response = $this->response = $request->send();
			$gatewayresponse->setOmnipayResponse($response);
			//update payment model
			if (GatewayInfo::is_manual($this->payment->Gateway)) {
				//initiate manual payment
				$this->createMessage('AuthorizedResponse', $response);
				$this->payment->Status = 'Authorized';
				$gatewayresponse->setMessage("Manual payment authorised");
			} elseif ($response->isSuccessful()) {
				//successful payment
				$this->createMessage('PurchasedResponse', $response);
				$this->payment->Status = 'Captured';
				$gatewayresponse->setMessage("Payment successful");
				$this->payment->extend('onCaptured', $gatewayresponse);
			} elseif ($response->isRedirect()) {
				// redirect to off-site payment gateway
				$this->createMessage('PurchaseRedirectResponse', $response);
				$this->payment->Status = 'Authorized';
				$gatewayresponse->setMessage("Redirecting to gateway");
			} else {
				//handle error
				$this->createMessage('PurchaseError', $response);
				$this->payment->Status = 'Void';
				$gatewayresponse->setMessage(
					"Error (".$response->getCode()."): ".$response->getMessage()
				);
			}
			$this->payment->write();
		} catch (Omnipay\Common\Exception\OmnipayException $e) {
			$this->createMessage('PurchaseError', $e);
			$this->payment->Status = 'Void';
			$this->payment->write();
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
	public function completePurchase() {
		$gatewayresponse = $this->createGatewayResponse();
		$request = $this->oGateway()->completePurchase(array(
			'amount' => (float) $this->payment->MoneyAmount
		));
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
				$this->payment->Status = 'Void';
				$this->payment->write();
			}
		} catch (Omnipay\Common\Exception\OmnipayException $e) {
			$this->createMessage("CompletePurchaseError", $e);
		}

		return $gatewayresponse;
	}

	/**
	 * @return \Omnipay\Common\CreditCard
	 */
	protected function getCreditCard($data) {
		return new CreditCard($data);
	}

}
