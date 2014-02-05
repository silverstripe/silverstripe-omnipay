<?php

use Omnipay\Common\CreditCard;

class PurchaseService extends PaymentService{

	/**
	 * Attempt to make a payment
	 * @param  array $data returnUrl/cancelUrl + customer creditcard
	 * and billing/shipping details.
	 * @return ResponseInterface omnipay's response class,
	 * specific to the chosen gateway.
	 */
	public function purchase($data = array()) {
		if ($this->payment->Status !== "Created") {
			return null; //could be handled better? send payment response?
		}
		if (!$this->payment->isInDB()) {
			$this->payment->write();
		}
		$this->returnurl = isset($data['returnUrl']) ?
							$data['returnUrl'] :
							$this->returnurl;
		$this->cancelurl = isset($data['cancelUrl']) ?
							$data['cancelUrl'] :
							$this->cancelurl;
		$message = $this->createMessage('PurchaseRequest');
		$request = $this->oGateway()->purchase(array(
			'card' => new CreditCard($data),
			'amount' => (float) $this->payment->MoneyAmount,
			'currency' => $this->payment->MoneyCurrency,
			'transactionId' => $message->Identifier,
			'clientIp' => isset($data['clientIp']) ? $data['clientIp'] : null,
			'returnUrl' => PaymentGatewayController::get_return_url(
								$message, 'complete', $this->returnurl
							),
			'cancelUrl' => PaymentGatewayController::get_return_url(
								$message, 'cancel', $this->cancelurl
							)
		));
		$this->logToFile($request->getParameters());
		$gatewayresponse = $this->createGatewayResponse();
		try {
			$response = $this->response = $request->send();
			//update payment model
			if (GatewayInfo::is_manual($this->payment->Gateway)) {
				$this->createMessage('AuthorizedResponse', $response);
				$this->payment->Status = 'Authorized';
				$this->payment->write();
				$gatewayresponse->setMessage("Manual payment authorised");
			} elseif ($response->isSuccessful()) {
				$this->createMessage('PurchasedResponse', $response);
				$this->payment->Status = 'Captured';
				$this->payment->write();
				$gatewayresponse->setMessage("Payment successful");
				$this->extend('onCaptured', $gatewayresponse);
			} elseif ($response->isRedirect()) {
				// redirect to off-site payment gateway
				$this->createMessage('PurchaseRedirectResponse', $response);
				$this->payment->Status = 'Authorized';
				$this->payment->write();
				$gatewayresponse->setMessage("Redirecting to gateway");
			} else {
				$this->createMessage('PurchaseError', $response);
				$gatewayresponse->setMessage(
					"Error (".$response->getCode()."): ".$response->getMessage()
				);
			}
			$gatewayresponse->setOmnipayResponse($response);
		} catch (Exception $e) {
			//TODO: only handle 'some' exceptions, throw the rest
			$this->createMessage('PurchaseError', $e->getMessage());
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
			if ($response->isSuccessful()) {
				$this->createMessage('PurchasedResponse', $response);
				$this->payment->Status = 'Captured';
				$this->payment->write();
				$this->extend('onCaptured', $gatewayresponse);
			} else {
				$this->createMessage('CompletePurchaseError', $response);
			}
			$gatewayresponse->setOmnipayResponse($response);
		} catch (\Exception $e) {
			$this->createMessage("CompletePurchaseError", $e->getMessage());
		}

		return $gatewayresponse;
	}

}
