<?php

class RefundService extends PaymentService{

	/**
	 * Return money to the previously charged credit card.
	 * @return PaymentResponse encapsulated response info
	 */
	public function refund($data = array()) {
		if ($this->payment->Status !== 'Captured') {
			return null; //could be handled better? send payment response?
		}
		if (!$this->payment->isInDB()) {
			$this->payment->write();
		}

		if(empty($data['receipt'])) {
			return null;
		}

		$message = $this->createMessage('RefundRequest');
		$message->write();
		$request = $this->oGateway()->refund(array_merge(
			$data,
			array(
				'amount' => (float) $this->payment->MoneyAmount,
				'receipt' => (int) $data['receipt'],
			)
		));
		$this->logToFile($request->getParameters(), 'RefundRequest_post');
		$gatewayresponse = $this->createGatewayResponse();
		try {
			$response = $this->response = $request->send();
			//update payment model
			if ($response->isSuccessful()) {
				//successful payment
				$this->createMessage('RefundedResponse', $response);
				$this->payment->Status = 'Refunded';
				$gatewayresponse->setMessage('Payment refunded');
				$this->payment->extend('onRefunded', $gatewayresponse);
			} else {
				//handle error
				$this->createMessage('RefundError', $response);
				$gatewayresponse->setMessage(
					"Error (".$response->getCode()."): ".$response->getMessage()
				);
			}
			$this->payment->write();
			$gatewayresponse->setOmnipayResponse($response);
		} catch (Omnipay\Common\Exception\OmnipayException $e) {
			$this->createMessage('GatewayErrorMessage', $e);
			$gatewayresponse->setMessage($e->getMessage());
		}

		return $gatewayresponse;
	}

}
