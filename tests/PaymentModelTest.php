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
}
