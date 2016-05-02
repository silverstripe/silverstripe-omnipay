<?php
use SilverStripe\Omnipay\GatewayInfo;

class PaymentModelTest extends PaymentTest
{

    public function testParameterSetup()
    {
        $payment = Payment::create()
                    ->init("Manual", 23.56, "NZD");

        $this->assertEquals("Created", $payment->Status);
        $this->assertEquals(23.56, $payment->Amount);
        $this->assertEquals("NZD", $payment->Currency);
        $this->assertEquals("Manual", $payment->Gateway);
    }

    public function testCMSFields()
    {
        $fields = Payment::create()->getCMSFields();
    }

    public function testTitle()
    {
        $oldLocale = i18n::get_locale();

        $payment = $this->objFromFixture("Payment", "payment1");

        i18n::set_locale('en_US');
        i18n::get_translator('core')->getAdapter()->addTranslation(array(
            'Gateway.Manual' => 'Manual',
            'Payment.TitleTemplate' => '{Gateway} {Money} %d/%m/%Y'
        ), 'en_US');

        $this->assertEquals(
            'Manual NZ$20.23 10/10/2013',
            $payment->getTitle()
        );

        i18n::get_translator('core')->getAdapter()->addTranslation(array(
            'Gateway.Manual' => 'Invoice',
            'Payment.TitleTemplate' => '{Money} via {Gateway} on %Y-%m-%d'
        ), 'en_US');

        $this->assertEquals(
            'NZ$20.23 via Invoice on 2013-10-10',
            $payment->getTitle()
        );

        i18n::get_translator('core')->getAdapter()->addTranslation(array(
            'Gateway.Manual' => 'Rechnung',
            'Payment.TitleTemplate' => '{Money} per {Gateway} am %d.%m.%Y'
        ), 'en_US');

        $this->assertEquals(
            'NZ$20.23 per Rechnung am 10.10.2013',
            $payment->getTitle()
        );

        $payment->Gateway = 'My%Strange%Gatewayname';
        $payment->Money->setCurrency('EUR');

        $this->assertEquals(
            '€20.23 per My%Strange%Gatewayname am 10.10.2013',
            $payment->getTitle()
        );

        i18n::set_locale($oldLocale);
    }

    public function testSupportedGateways()
    {
        $gateways = GatewayInfo::getSupportedGateways();
        $this->assertArrayHasKey('PayPal_Express', $gateways);
        $this->assertArrayHasKey('PaymentExpress_PxPay', $gateways);
        $this->assertArrayHasKey('Manual', $gateways);
        $this->assertArrayHasKey('Dummy', $gateways);
    }

    public function testCreateIdentifier()
    {
        $payment = new Payment();
        $payment->write();
        $this->assertNotNull($payment->Identifier);
        $this->assertNotEquals('', $payment->Identifier);
        $this->assertEquals(30, strlen($payment->Identifier));
    }

    public function testChangeIdentifier()
    {
        $payment = $this->objFromFixture('Payment', 'payment2');
        $payment->Identifier = "somethingelse";
        $this->assertEquals("UNIQUEHASH23q5123tqasdf", $payment->Identifier);
    }

    public function testTargetUrls()
    {
        $payment = new Payment();
        $payment->setSuccessUrl("abc/123");

        // setting the success Url should also set the failure url (if not set)
        $this->assertEquals("abc/123", $payment->SuccessUrl);
        $this->assertEquals("abc/123", $payment->FailureUrl);


        $payment->setFailureUrl("xyz/blah/2345235?andstuff=124124#hash");
        $this->assertEquals("xyz/blah/2345235?andstuff=124124#hash", $payment->FailureUrl);

        $payment->setSuccessUrl("abc/updated");
        $this->assertEquals("abc/updated", $payment->SuccessUrl);
        $this->assertEquals("xyz/blah/2345235?andstuff=124124#hash", $payment->FailureUrl);
    }

    public function testGatewayMutability()
    {
        $payment = Payment::create()->init('Manual', 120, 'EUR');

        $this->assertEquals($payment->Gateway, 'Manual');

        $payment->Gateway = 'Dummy';
        $this->assertEquals($payment->Gateway, 'Dummy');

        $payment->Status = 'Authorized';
        $payment->Gateway = 'Manual';
        $this->assertEquals(
            $payment->Gateway,
            'Dummy',
            'Payment status should be immutable once it\'s no longer Created'
        );
    }

    public function testCanCapture()
    {
        $payment = Payment::create()->init('Manual', 120, 'EUR');

        // cannot capture new payment
        $this->assertFalse($payment->canCapture());
        $this->assertFalse($payment->canCapture(true));

        $payment->Status = 'Authorized';

        $this->assertTrue($payment->canCapture());
        $this->assertTrue($payment->canCapture(true));

        Config::inst()->update('GatewayInfo', 'Manual', array(
            'can_capture' => false
        ));

        $this->assertFalse($payment->canCapture());
        $this->assertFalse($payment->canCapture(true));

        Config::inst()->update('GatewayInfo', 'Manual', array(
            'can_capture' => 'full'
        ));

        $this->assertTrue($payment->canCapture());
        $this->assertFalse($payment->canCapture(true));

        Config::inst()->update('GatewayInfo', 'Manual', array(
            'can_capture' => 'partial'
        ));

        $this->assertTrue($payment->canCapture());
        $this->assertTrue($payment->canCapture(true));
    }

    public function testCanRefund()
    {
        $payment = Payment::create()->init('Manual', 120, 'EUR');

        // cannot refund new payment
        $this->assertFalse($payment->canRefund());
        $this->assertFalse($payment->canRefund(true));

        $payment->Status = 'Captured';

        $this->assertTrue($payment->canRefund());
        $this->assertTrue($payment->canRefund(true));

        Config::inst()->update('GatewayInfo', 'Manual', array(
            'can_refund' => false
        ));

        $this->assertFalse($payment->canRefund());
        $this->assertFalse($payment->canRefund(true));

        Config::inst()->update('GatewayInfo', 'Manual', array(
            'can_refund' => 'full'
        ));

        $this->assertTrue($payment->canRefund());
        $this->assertFalse($payment->canRefund(true));

        Config::inst()->update('GatewayInfo', 'Manual', array(
            'can_refund' => 'partial'
        ));

        $this->assertTrue($payment->canRefund());
        $this->assertTrue($payment->canRefund(true));
    }

    public function testCanVoid()
    {
        $payment = Payment::create()->init('Manual', 120, 'EUR');

        // cannot void new payment
        $this->assertFalse($payment->canVoid());

        $payment->Status = 'Authorized';

        $this->assertTrue($payment->canVoid());

        Config::inst()->update('GatewayInfo', 'Manual', array(
            'can_void' => false
        ));

        $this->assertFalse($payment->canVoid());

        Config::inst()->update('GatewayInfo', 'Manual', array(
            'can_void' => true
        ));

        $this->assertTrue($payment->canVoid());
    }

    public function testMaxCaptureAmount()
    {
        $payment = Payment::create()->init('Dummy', 120, 'EUR');
        // If payment isn't Authorized, return 0
        $this->assertEquals(0, $payment->getMaxCaptureAmount());

        $payment->Status = 'Authorized';

        Config::inst()->update('GatewayInfo', 'Dummy', array('max_capture' => '30'));
        $this->assertEquals('150.00', $payment->getMaxCaptureAmount());

        Config::inst()->update('GatewayInfo', 'Dummy', array('max_capture' => '30%'));
        $this->assertEquals('156.00', $payment->getMaxCaptureAmount());

        Config::inst()->update('GatewayInfo', 'Dummy', array('max_capture' => '17%'));
        $this->assertEquals('140.40', $payment->getMaxCaptureAmount());

        Config::inst()->remove('GatewayInfo', 'Dummy');
        Config::inst()->update('GatewayInfo', 'Dummy', array('max_capture' => array(
            'amount' => array(
                'USD' => 80,
                'EUR' => 70,
                'TRY' => 224,
                'GBP' => -10 // invalid value, should result in no increase
            ),
            'percent' => '20%'
        )));

        $this->assertEquals('144.00', $payment->getMaxCaptureAmount());
        $payment->Status = 'Created';
        $payment->MoneyAmount = '900.00';
        $payment->Status = 'Authorized';
        // should use the fixed increase from EUR and USD, since the percentage increase would exceed the fixed amount
        $this->assertEquals('970.00', $payment->getMaxCaptureAmount());
        $payment->MoneyCurrency = 'USD';
        $this->assertEquals('980.00', $payment->getMaxCaptureAmount());

        // should use the percent increase, since 0.2 of 900 won't exceed the fixed amount
        $payment->MoneyCurrency = 'TRY';
        $this->assertEquals('1080.00', $payment->getMaxCaptureAmount());

        // no increase with invalid setting
        $payment->MoneyCurrency = 'GBP';
        $this->assertEquals('900.00', $payment->getMaxCaptureAmount());

        // test with a small payment amount
        $payment->Status = 'Created';
        $payment->init('Dummy', '1.19', 'EUR');
        $payment->Status = 'Authorized';
        $this->assertEquals('1.42', $payment->getMaxCaptureAmount());
    }
}
