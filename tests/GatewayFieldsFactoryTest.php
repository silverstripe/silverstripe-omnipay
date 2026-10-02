<?php

declare(strict_types=1);

namespace SilverStripe\Omnipay\Tests;

use Omnipay\Common\CreditCard;
use SilverStripe\Omnipay\GatewayFieldsFactory;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Omnipay\GatewayInfo;

class GatewayFieldsFactoryTest extends SapphireTest
{
    // Expected credit card fields
    protected array $ccFields = [
        'type',
        'name',
        'number',
        'startMonth',
        'startYear',
        'expiryMonth',
        'expiryYear',
        'cvv',
        'issueNumber'
    ];

    // expected billing fields
    protected array $billingFields = [
        'billingAddress1',
        'billingAddress2',
        'billingCity',
        'billingPostcode',
        'billingState',
        'billingCountry',
        'billingPhone'
    ];

    // expected shipping fields
    protected array $shippingFields = [
        'shippingAddress1',
        'shippingAddress2',
        'shippingCity',
        'shippingPostcode',
        'shippingState',
        'shippingCountry',
        'shippingPhone'
    ];

    // expected company fields
    protected array $companyFields = ['company'];

    // expected email fields
    protected array $emailFields = ['email'];

    protected GatewayFieldsFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        // tests can potentially fail if we just update due to settings already defined persisting, so we'll remove
        // it first
        Config::modify()->remove(GatewayFieldsFactory::class, 'rename');

        $this->factory =  GatewayFieldsFactory::create(null, [
            'Card',
            'Billing',
            'Shipping',
            'Company',
            'Email'
        ]);
    }

    public function testAllFieldGroups(): void
    {
        $fieldList = $this->factory->getFields();

        // All fields should be returned
        $this->assertEquals(array_merge(
            $this->ccFields,
            $this->billingFields,
            $this->shippingFields,
            $this->companyFields,
            $this->emailFields
        ), array_keys($fieldList->getDataFields()));
    }

    public function testCCFields(): void
    {
        // Create a gateway-factory without a gateway
        $gatewayFieldsFactory = GatewayFieldsFactory::create(null, ['Card']);

        $fieldList = $gatewayFieldsFactory->getFields();

        $this->assertEquals($this->ccFields, array_keys($fieldList->getDataFields()));

        $this->assertEquals($this->ccFields, array_keys($this->factory->getCardFields()->getDataFields()));
    }

    public function testBillingFields(): void
    {
        // Create a gateway-factory without a gateway
        $gatewayFieldsFactory = GatewayFieldsFactory::create(null, ['Billing']);

        $fieldList = $gatewayFieldsFactory->getFields();

        $this->assertEquals($this->billingFields, array_keys($fieldList->getDataFields()));

        $this->assertEquals($this->billingFields, array_keys($this->factory->getBillingFields()->getDataFields()));
    }

    public function testShippingFields(): void
    {
        // Create a gateway-factory without a gateway
        $gatewayFieldsFactory = GatewayFieldsFactory::create(null, ['Shipping']);

        $fieldList = $gatewayFieldsFactory->getFields();

        $this->assertEquals($this->shippingFields, array_keys($fieldList->getDataFields()));

        $this->assertEquals($this->shippingFields, array_keys($this->factory->getShippingFields()->getDataFields()));
    }

    public function testCompanyFields(): void
    {
        // Create a gateway-factory without a gateway
        $gatewayFieldsFactory = GatewayFieldsFactory::create(null, ['Company']);

        $fieldList = $gatewayFieldsFactory->getFields();

        $this->assertEquals($this->companyFields, array_keys($fieldList->getDataFields()));

        $this->assertEquals($this->companyFields, array_keys($this->factory->getCompanyFields()->getDataFields()));
    }

    public function testEmailFields(): void
    {
        // Create a gateway-factory without a gateway
        $gatewayFieldsFactory = GatewayFieldsFactory::create(null, ['Email']);

        $fieldList = $gatewayFieldsFactory->getFields();

        $this->assertEquals($this->emailFields, array_keys($fieldList->getDataFields()));

        $this->assertEquals($this->emailFields, array_keys($this->factory->getEmailFields()->getDataFields()));
    }

    public function testCardTypes(): void
    {
        $types = $this->factory->getCardTypes();
        $creditCard = new CreditCard();
        $this->assertEquals(array_keys($creditCard->getSupportedBrands()), array_keys($types));
    }

    public function testRequiredFields(): void
    {
        Config::modify()->merge(GatewayInfo::class, 'Dummy', [
            'required_fields' => [
                'billingAddress1',
                'city',
                'country',
                'email',
                'company'
            ],
            'is_offsite' => false
        ]);

        Config::modify()->merge(GatewayInfo::class, 'PayPal_Express', [
            'required_fields' => [
                'billingAddress1',
                'city',
                'country',
                'email',
                'company'
            ]
        ]);

        $factory = GatewayFieldsFactory::create('Dummy', [
            'Card',
            'Billing',
            'Shipping',
            'Company',
            'Email'
        ]);

        $fields = $factory->getFields();

        $defaults = [
            // default required CC fields for gateways that aren't manual and aren't offsite
            'name',
            'number',
            'expiryMonth',
            'expiryYear',
            'cvv',
            // end CC fields
            'billingAddress1',
            'billingCity',
            'billingCountry',
            'shippingCity',
            'shippingCountry',
            'company',
            'email'
        ];

        $this->assertEquals($this->factory->getFieldName($defaults), array_keys($fields->getDataFields()));

        // Same procedure with offsite gateway should not return the CC fields

        $factory = GatewayFieldsFactory::create('PayPal_Express', [
            'Card',
            'Billing',
            'Shipping',
            'Company',
            'Email'
        ]);

        $fields = $factory->getFields();

        $pxDefaults = [
            'billingAddress1',
            'billingCity',
            'billingCountry',
            'shippingCity',
            'shippingCountry',
            'company',
            'email'
        ];

        $this->assertEquals($this->factory->getFieldName($pxDefaults), array_keys($fields->getDataFields()));
    }

    public function testRenamedFields(): void
    {
        Config::modify()->merge(GatewayInfo::class, 'Dummy', [
            'is_offsite' => false
        ]);

        Config::modify()->merge(GatewayFieldsFactory::class, 'rename', [
            'prefix' => 'prefix_',
            'name' => 'testName',
            'number' => 'testNumber',
            'expiryMonth' => 'testExpiryMonth',
            'expiryYear' => 'testExpiryYear',
            'Dummy' => [
                'prefix' => 'dummy_',
                'number' => 'dummyCCnumber'
            ]
        ]);

        $factory = GatewayFieldsFactory::create(null, [
            'Card'
        ]);

        $fields = $factory->getFields();

        $expected = [
            'prefix_type',
            'prefix_testName',
            'prefix_testNumber',
            'prefix_startMonth',
            'prefix_startYear',
            'prefix_testExpiryMonth',
            'prefix_testExpiryYear',
            'prefix_cvv',
            'prefix_issueNumber'
        ];

        $this->assertEquals($expected, array_keys($fields->getDataFields()));

        $factory = GatewayFieldsFactory::create('Dummy', [
            'Card'
        ]);

        $fields = $factory->getFields();

        $expected = [
            'dummy_testName',
            'dummy_dummyCCnumber',
            'dummy_testExpiryMonth',
            'dummy_testExpiryYear',
            'dummy_cvv',
        ];

        $this->assertEquals($expected, array_keys($fields->getDataFields()));
    }

    public function testNormalizeFormData(): void
    {
        Config::modify()->set(GatewayFieldsFactory::class, 'rename', [
            'prefix' => 'prefix_',
            'name' => 'testName',
            'number' => 'testNumber',
            'expiryMonth' => 'testExpiryMonth',
            'expiryYear' => 'testExpiryYear',
            'Dummy' => [
                'prefix' => 'dummy_',
                'number' => 'dummyCCnumber'
            ]
        ]);

        // Test global rename
        $factory = GatewayFieldsFactory::create();
        $this->assertEquals(
            $factory->normalizeFormData(
                [
                    'prefix_testName' => 'Reece Alexander',
                    'prefix_testNumber' => '4242424242424242',
                    'prefix_testExpiryMonth' => '11',
                    'prefix_testExpiryYear' => '2016',
                    'someOtherFormValue' => 'Should be unchanged',
                    // Ensure other fields are not affected by prefix change!
                    'prefix_prefixedValue' => 'Something'
                ]
            ),
            [
                'name' => 'Reece Alexander',
                'number' => '4242424242424242',
                'expiryMonth' => '11',
                'expiryYear' => '2016',
                'someOtherFormValue' => 'Should be unchanged',
                'prefix_prefixedValue' => 'Something'
            ]
        );
        // Test gateway specific rename
        $factory = GatewayFieldsFactory::create('Dummy');

        $this->assertEquals(
            $factory->normalizeFormData(
                [
                    'dummy_testName' => 'Reece Alexander',
                    'dummy_dummyCCnumber' => '4242424242424242',
                    'dummy_testExpiryMonth' => '11',
                    'dummy_testExpiryYear' => '2016',
                    'someOtherFormValue' => 'Should be unchanged',
                    'dummy_prefixedValue' => 'Something'
                ]
            ),
            [
                'name' => 'Reece Alexander',
                'number' => '4242424242424242',
                'expiryMonth' => '11',
                'expiryYear' => '2016',
                'someOtherFormValue' => 'Should be unchanged',
                'dummy_prefixedValue' => 'Something'
            ]
        );
    }
}
