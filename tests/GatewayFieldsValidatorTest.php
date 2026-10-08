<?php

declare(strict_types=1);

namespace SilverStripe\Omnipay\Tests;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Omnipay\Forms\GatewayFieldsValidator;
use SilverStripe\Omnipay\GatewayFieldsFactory;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\Model\Payment;

class GatewayFieldsValidatorTest extends SapphireTest
{
    private const VALID_CARD = [
        'name' => 'Fred',
        'number' => '4111 1111 1111 1111',
        'expiryMonth' => '12',
        'expiryYear' => '2027',
        'cvv' => '123',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        DBDatetime::set_mock_now('2026-10-08 12:00:00');
        Config::modify()->remove(GatewayFieldsFactory::class, 'rename');
        Config::modify()->set(Payment::class, 'allowed_gateways', ['Dummy']);
        Config::modify()->set(GatewayInfo::class, 'Dummy', ['is_offsite' => false, 'required_fields' => ['email']]);
    }

    protected function tearDown(): void
    {
        DBDatetime::clear_mock_now();
        parent::tearDown();
    }

    public function testFactoryCreatesValidator(): void
    {
        $factory = GatewayFieldsFactory::create('Dummy');
        $validator = $factory->getValidator(['company']);

        $this->assertInstanceOf(GatewayFieldsValidator::class, $validator);
        $this->assertSame($factory, $validator->getFactory());
        // Gateway required fields, onsite card fields and additional fields
        foreach (['email', 'number', 'cvv', 'company'] as $field) {
            $this->assertContains($field, $validator->getRequired());
        }
    }

    public function testValidCard(): void
    {
        $result = $this->validate(self::VALID_CARD + ['email' => 'fred@example.com']);

        $this->assertTrue($result['valid'], print_r($result['errors'], true));
    }

    public function testRequiredFields(): void
    {
        $result = $this->validate(['email' => ''] + self::VALID_CARD);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('email', $result['errors']);
    }

    public function testInvalidCardData(): void
    {
        $result = $this->validate([
            'number' => '4111 1111 1111 1112',
            'expiryMonth' => '9',
            'expiryYear' => '2026',
            'cvv' => '12a',
        ] + self::VALID_CARD + ['email' => 'fred@example.com']);

        $this->assertFalse($result['valid']);
        $this->assertEqualsCanonicalizing(['number', 'expiryMonth', 'cvv'], array_keys($result['errors']));
    }

    public function testCurrentMonthIsNotExpired(): void
    {
        $result = $this->validate(['expiryMonth' => '10', 'expiryYear' => '2026'] + self::VALID_CARD + [
            'email' => 'fred@example.com'
        ]);

        $this->assertTrue($result['valid'], print_r($result['errors'], true));
    }

    public function testRenamedFields(): void
    {
        Config::modify()->set(GatewayFieldsFactory::class, 'rename', ['number' => 'ccNumber']);

        $data = self::VALID_CARD + ['email' => 'fred@example.com'];
        $data['ccNumber'] = '1234';
        unset($data['number']);

        $result = $this->validate($data);

        $this->assertFalse($result['valid']);
        $this->assertSame(['ccNumber'], array_keys($result['errors']));
    }

    /**
     * @param array<string, string> $data
     * @return array{valid: bool, errors: array<string, string>}
     */
    private function validate(array $data): array
    {
        $factory = GatewayFieldsFactory::create('Dummy', ['Card', 'Email']);
        $validator = $factory->getValidator();
        $form = Form::create(Controller::curr(), 'TestForm', $factory->getFields(), FieldList::create(), $validator);
        $form->loadDataFrom($data);

        $result = $validator->validate();
        $errors = [];
        foreach ($result->getMessages() as $message) {
            $errors[$message['fieldName']] = $message['message'];
        }

        return ['valid' => $result->isValid(), 'errors' => $errors];
    }
}
