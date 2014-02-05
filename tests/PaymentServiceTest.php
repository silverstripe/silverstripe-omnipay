<?php

class PaymentServiceTest extends PaymentTest{

	public function testRedirectUrl() {
		$service = PurchaseService::create(new Payment())
					->setReturnUrl("abc/123")
					->setCancelUrl("xyz/blah/2345235?andstuff=124124#hash");
		$this->assertEquals("abc/123",$service->getReturnUrl());
		$this->assertEquals("xyz/blah/2345235?andstuff=124124#hash",$service->getCancelUrl());
	}

}
