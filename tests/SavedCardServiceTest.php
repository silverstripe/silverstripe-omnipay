<?php

class SavedCardServiceTest extends PaymentTest {

	public function testCreateCard() {
		$payment = $this->payment->setGateway('Stripe');
		$service = new SavedCardService($payment);
		$this->setMockHttpResponse('Stripe/Mock/CreateCardSuccess.txt');
		$response = $service->createCard(array(
			'cardName' => 'My Test Card 1',
			'number' => '4111111111111111',
			'expiryMonth' => '5',
			'expiryYear' => date("Y", strtotime("+1 year"))
		));
		$this->assertTrue($response->isSuccessful());
		$this->assertFalse($response->isRedirect());
		$this->assertSame("Created", $payment->Status, 'does not process payment');

		/** @var SavedCreditCard $card */
		$card = $payment->SavedCreditCard();
		Debug::dump($card);
		$this->assertNotNull($card);
		$this->assertInstanceOf('SavedCreditCard', $card);
		$this->assertEquals('My Test Card 1', $card->Name);
		$this->assertEquals('1111', $card->LastFourDigits);
		$this->assertEquals('cus_1MZSEtqSghKx99', $card->CardReference);

		//check messaging
		$this->assertDOSContains(array(
			array('ClassName' => 'CreateCardRequest'),
			array('ClassName' => 'CreateCardResponse'),
		), $payment->Messages());
	}

	public function testCreateCardWithNoName() {
		$payment = $this->payment->setGateway('Stripe');
		$service = new SavedCardService($payment);
		$this->setMockHttpResponse('Stripe/Mock/CreateCardSuccess.txt');
		$response = $service->createCard(array(
			'number' => '4111-1111-1111-1111',
			'expiryMonth' => '5',
			'expiryYear' => date("Y", strtotime("+1 year"))
		));
		$this->assertTrue($response->isSuccessful());

		/** @var SavedCreditCard $card */
		$card = $payment->SavedCreditCard();
		$this->assertEquals('************1111', $card->Name);
		$this->assertEquals('1111', $card->LastFourDigits);
	}

	public function testUpdateCard() {
		$this->markTestIncomplete('Need to test/implement updateCard');
	}

	public function testDeleteCard() {
		$this->markTestIncomplete('Need to test/implement deleteCard');
	}

	public function testPayWithCard() {
		$card = $this->getTestPaymentWithSavedCard()->SavedCreditCard();
		$payment = $this->payment->setGateway('Stripe');
		$payment->setAmount(10.00);
		$payment->setCurrency('NZD');
		$payment->SavedCardID = $card->ID;
		$payment->write();
		$service = new PurchaseService($payment);
		$this->setMockHttpResponse('Stripe/Mock/PurchaseSuccess.txt');//add success mock response from file
		$response = $service->purchase();
		$this->assertTrue($response->isSuccessful());
		$this->assertFalse($response->isRedirect());
		$this->assertSame("Captured", $payment->Status, "has the payment been captured");

		//check messaging
		$this->assertDOSContains(array(
			array('ClassName' => 'PurchaseRequest'),
			array('ClassName' => 'PurchasedResponse')
		), $payment->Messages());
	}


	/**
	 * @return Payment
	 */
	protected function getTestPaymentWithSavedCard() {
		$payment = $this->payment->setGateway('Stripe');
		$payment->setAmount(0);
		$service = new SavedCardService($payment);
		$this->setMockHttpResponse('Stripe/Mock/CreateCardSuccess.txt');
		$response = $service->createCard(array(
			'cardName' => 'My Test Card 1',
			'number' => '4111111111111111',
			'expiryMonth' => '5',
			'expiryYear' => date("Y", strtotime("+1 year"))
		));
		return $payment;
	}
}