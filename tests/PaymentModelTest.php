<?php

declare(strict_types=1);

namespace SilverStripe\Omnipay\Tests;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\i18n\i18n;
use SilverStripe\i18n\Messages\Symfony\SymfonyMessageProvider;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Tests\Service\TestRandomGenerator;
use SilverStripe\Security\RandomGenerator;

class PaymentModelTest extends FunctionalTest
{
    use PaymentTestTrait;

    protected static $fixture_file = 'PaymentTest.yml';

    protected $autoFollowRedirection = false;

    protected function setUp(): void
    {
        parent::setUp();

        Payment::config()->set('allowed_gateways', [
            'PayPal_Express',
            'PaymentExpress_PxPay',
            'Manual',
            'Dummy'
        ]);

        Config::modify()->merge(GatewayInfo::class, 'Manual', [
            'can_capture' => true,
            'can_refund' => true,
            'can_void' => true
        ]);
    }

    public function testParameterSetup(): void
    {
        $payment = Payment::create()
                    ->init("Manual", 23.56, "NZD");

        $this->assertEquals("Created", $payment->Status);
        $this->assertEqualsWithDelta(23.56, $payment->Amount, PHP_FLOAT_EPSILON);
        $this->assertEquals("NZD", $payment->Currency);
        $this->assertEquals("Manual", $payment->Gateway);
    }


    public function testCMSFields(): void
    {
        $fieldList = Payment::create()->getCMSFields();

        $this->assertInstanceOf(FieldList::class, $fieldList);
    }

    public function testTitle(): void
    {
        $oldLocale = i18n::get_locale();

        $payment = $this->objFromFixture(Payment::class, "payment1");
        i18n::set_locale('en_US');
        $messageProvider = i18n::getMessageProvider();
        $this->assertInstanceOf(SymfonyMessageProvider::class, $messageProvider);
        $messageCatalogue = $messageProvider->getTranslator()->getCatalogue('en_US');
        $messageCatalogue->set('Gateway.Manual', 'Manual');

        $this->assertEquals(
            'Manual NZ$20.23 10/10/2013',
            $payment->getTitle()
        );

        $payment->Gateway = 'My%Strange%Gatewayname';
        $payment->Money->setCurrency('EUR');

        $this->assertEquals(
            'My%Strange%Gatewayname €20.23 10/10/2013',
            $payment->getTitle()
        );

        i18n::set_locale($oldLocale);
    }

    public function testSupportedGateways(): void
    {
        $gateways = GatewayInfo::getSupportedGateways();
        $this->assertArrayHasKey('PayPal_Express', $gateways);
        $this->assertArrayHasKey('PaymentExpress_PxPay', $gateways);
        $this->assertArrayHasKey('Manual', $gateways);
        $this->assertArrayHasKey('Dummy', $gateways);
    }

    public function testCreateIdentifier(): void
    {
        $payment = Payment::create();
        $payment->write();
        $this->assertNotNull($payment->Identifier);
        $this->assertNotEquals('', $payment->Identifier);
        $this->assertSame(30, strlen($payment->Identifier));
    }

    public function testChangeIdentifier(): void
    {
        $payment = $this->objFromFixture(Payment::class, 'payment2');
        $payment->Identifier = "somethingelse";
        $this->assertSame("UNIQUEHASH23q5123tqasdf", $payment->Identifier);
    }

    public function testTargetUrls(): void
    {
        $payment = Payment::create();
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

    public function testGatewayMutability(): void
    {
        $payment = Payment::create()->init('Manual', 120, 'EUR');

        $this->assertEquals('Manual', $payment->Gateway);

        $payment->Gateway = 'Dummy';
        $this->assertSame('Dummy', $payment->Gateway);

        $payment->Status = 'Authorized';
        $payment->Gateway = 'Manual';
        $this->assertSame(
            'Dummy',
            $payment->Gateway,
            "Payment status should be immutable once it's no longer Created"
        );
    }

    public function testCanCapture(): void
    {
        $this->logInWithPermission('CAPTURE_PAYMENTS');

        $payment = Payment::create()->init('Manual', 120, 'EUR');

        // cannot capture new payment
        $this->assertFalse($payment->canCapture());
        $this->assertFalse($payment->canCapture(null, true));

        $payment->Status = 'Authorized';

        $this->assertTrue($payment->canCapture());
        $this->assertTrue($payment->canCapture(null, true));

        Config::modify()->merge(GatewayInfo::class, 'Manual', [
            'can_capture' => false
        ]);

        $this->assertFalse($payment->canCapture());
        $this->assertFalse($payment->canCapture(null, true));

        Config::modify()->merge(GatewayInfo::class, 'Manual', [
            'can_capture' => 'full'
        ]);

        $this->assertTrue($payment->canCapture());
        $this->assertFalse($payment->canCapture(null, true));

        Config::modify()->merge(GatewayInfo::class, 'Manual', [
            'can_capture' => 'partial'
        ]);

        $this->assertTrue($payment->canCapture());
        $this->assertTrue($payment->canCapture(null, true));

        // Login with some other permission
        $this->logInWithPermission('SOME_OTHER_PERMISSION');
        $this->assertFalse($payment->canCapture());
        $this->assertFalse($payment->canCapture(null, true));
    }

    public function testCanRefund(): void
    {
        $this->logInWithPermission('REFUND_PAYMENTS');
        $payment = Payment::create()->init('Manual', 120, 'EUR');

        // cannot refund new payment
        $this->assertFalse($payment->canRefund());
        $this->assertFalse($payment->canRefund(null, true));

        $payment->Status = 'Captured';

        $this->assertTrue($payment->canRefund());
        $this->assertTrue($payment->canRefund(null, true));

        Config::modify()->merge(GatewayInfo::class, 'Manual', [
            'can_refund' => false
        ]);

        $this->assertFalse($payment->canRefund());
        $this->assertFalse($payment->canRefund(null, true));

        Config::modify()->merge(GatewayInfo::class, 'Manual', [
            'can_refund' => 'full'
        ]);

        $this->assertTrue($payment->canRefund());
        $this->assertFalse($payment->canRefund(null, true));

        Config::modify()->merge(GatewayInfo::class, 'Manual', [
            'can_refund' => 'partial'
        ]);

        $this->assertTrue($payment->canRefund());
        $this->assertTrue($payment->canRefund(null, true));

        // Login with some other permission
        $this->logInWithPermission('SOME_OTHER_PERMISSION');
        $this->assertFalse($payment->canRefund());
        $this->assertFalse($payment->canRefund(null, true));
    }

    public function testCanVoid(): void
    {
        $this->logInWithPermission('VOID_PAYMENTS');
        $payment = Payment::create()->init('Manual', 120, 'EUR');

        // cannot void new payment
        $this->assertFalse($payment->canVoid());

        $payment->Status = 'Authorized';

        $this->assertTrue($payment->canVoid());

        Config::modify()->merge(GatewayInfo::class, 'Manual', [
            'can_void' => false
        ]);

        $this->assertFalse($payment->canVoid());

        Config::modify()->merge(GatewayInfo::class, 'Manual', [
            'can_void' => true
        ]);

        $this->assertTrue($payment->canVoid());

        // Login with some other permission
        $this->logInWithPermission('SOME_OTHER_PERMISSION');
        $this->assertFalse($payment->canVoid());
    }

    public function testMaxCaptureAmount(): void
    {
        $payment = Payment::create()->init('Dummy', 120, 'EUR');
        // If payment isn't Authorized, return 0
        $this->assertEquals(0, $payment->getMaxCaptureAmount());

        $payment->Status = 'Authorized';

        Config::modify()->merge(GatewayInfo::class, 'Dummy', ['max_capture' => '30']);
        $this->assertEquals('150.00', $payment->getMaxCaptureAmount());

        Config::modify()->merge(GatewayInfo::class, 'Dummy', ['max_capture' => '30%']);
        $this->assertEquals('156.00', $payment->getMaxCaptureAmount());

        Config::modify()->merge(GatewayInfo::class, 'Dummy', ['max_capture' => '17%']);
        $this->assertEquals('140.40', $payment->getMaxCaptureAmount());

        Config::modify()
            ->remove('GatewayInfo', 'Dummy')
            ->set(GatewayInfo::class, 'Dummy', ['max_capture' => [
                'amount' => [
                    'USD' => 80,
                    'EUR' => 70,
                    'TRY' => 224,
                    'GBP' => -10 // invalid value, should result in no increase
                ],
                'percent' => '20%'
            ]]);

        $this->assertEquals('144.00', $payment->getMaxCaptureAmount());
        $payment->Status = 'Created';
        $payment->MoneyAmount = 900.0;
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
        $payment->init('Dummy', 1.19, 'EUR');
        $payment->Status = 'Authorized';
        $this->assertEquals('1.42', $payment->getMaxCaptureAmount());
    }

    /**
     *
     */
    public function testDuplicateIdentifiers(): void
    {
        $testRandomGenerator = TestRandomGenerator::create();
        $testRandomGenerator->addRandomTokens('token1', 'token1', 'token1', 'token2');
        Injector::inst()->registerService($testRandomGenerator, RandomGenerator::class);

        $payment1 = Payment::create();
        $payment1->write();
        $this->assertSame('token1', $payment1->Identifier);

        $payment2 = Payment::create();
        $payment2->write();
        $this->assertSame('token2', $payment2->Identifier);
    }

    /**
     *
     */
    public function testIdentifierLengthConfig(): void
    {
        Config::modify()->set(Payment::class, 'payment_identifier_length', 20);
        $payment = Payment::create();
        $payment->write();
        $this->assertSame(20, strlen($payment->Identifier));

        Config::modify()->set(Payment::class, 'payment_identifier_length', 30);
        $payment = Payment::create();
        $payment->setGateway('Manual');
        $payment->write();
        $this->assertSame(30, strlen($payment->Identifier));

        Config::modify()->merge(GatewayInfo::class, 'IdentifierFifteen', [
            'payment_identifier_length' => 15,
        ]);
        $payment = Payment::create();
        $payment->setGateway('IdentifierFifteen');
        $payment->write();
        $this->assertSame(15, strlen($payment->Identifier));
    }
}
