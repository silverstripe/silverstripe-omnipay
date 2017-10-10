<?php

namespace SilverStripe\Omnipay\Tests;

use SilverStripe\Omnipay\GatewayFieldsFactory;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Omnipay\GatewayInfo;

class GatewayFieldsFactoryTest extends SapphireTest
{
    // Expected credit card fields
    protected $ccFields = array(
        'type',
        'name',
        'number',
        'startMonth',
        'startYear',
        'expiryMonth',
        'expiryYear',
        'cvv',
        'issueNumber'
    );

    // expected billing fields
    protected $billingFields = array(
        'billingAddress1',
        'billingAddress2',
        'billingCity',
        'billingPostcode',
        'billingState',
        'billingCountry',
        'billingPhone'
    );

    // expected shipping fields
    protected $shippingFields = array(
        'shippingAddress1',
        'shippingAddress2',
        'shippingCity',
        'shippingPostcode',
        'shippingState',
        'shippingCountry',
        'shippingPhone'
    );

    // expected company fields
    protected $companyFields = array('company');

    // expected email fields
    protected $emailFields = array('email');

    /** @var GatewayFieldsFactory */
    protected $factory;

    public function setUp()
    {
        parent::setUp();
        // tests can potentially fail if we just update due to settings already defined persisting, so we'll remove
        // it first
        Config::inst()->remove('SilverStripe\Omnipay\GatewayFieldsFactory', 'rename');

        $this->factory =  new GatewayFieldsFactory(
            null,
            array(
            'Card',
            'Billing',
            'Shipping',
            'Company',
            'Email'
            )
        );
    }

    public function testAllFieldGroups()
    {
        $fields = $this->factory->getFields();

        // All fields should be returned
        $this->assertEquals(array_merge(
            $this->ccFields,
            $this->billingFields,
            $this->shippingFields,
            $this->companyFields,
            $this->emailFields
        ), array_keys($fields->dataFields()));
    }

    public function testCCFields()
    {
        // Create a gateway-factory without a gateway
        $factory = new GatewayFieldsFactory(
            null,
            array('Card')
        );

        $fields = $factory->getFields();

        $this->assertEquals($this->ccFields, array_keys($fields->dataFields()));

        $this->assertEquals($this->ccFields, array_keys($this->factory->getCardFields()->dataFields()));
    }

    public function testBillingFields()
    {
        // Create a gateway-factory without a gateway
        $factory = new GatewayFieldsFactory(
            null,
            array('Billing')
        );

        $fields = $factory->getFields();

        $this->assertEquals($this->billingFields, array_keys($fields->dataFields()));

        $this->assertEquals($this->billingFields, array_keys($this->factory->getBillingFields()->dataFields()));
    }

    public function testShippingFields()
    {
        // Create a gateway-factory without a gateway
        $factory = new GatewayFieldsFactory(
            null,
            array('Shipping')
        );

        $fields = $factory->getFields();

        $this->assertEquals($this->shippingFields, array_keys($fields->dataFields()));

        $this->assertEquals($this->shippingFields, array_keys($this->factory->getShippingFields()->dataFields()));
    }

    public function testCompanyFields()
    {
        // Create a gateway-factory without a gateway
        $factory = new GatewayFieldsFactory(
            null,
            array('Company')
        );

        $fields = $factory->getFields();

        $this->assertEquals($this->companyFields, array_keys($fields->dataFields()));

        $this->assertEquals($this->companyFields, array_keys($this->factory->getCompanyFields()->dataFields()));
    }

    public function testEmailFields()
    {
        // Create a gateway-factory without a gateway
        $factory = new GatewayFieldsFactory(
            null,
            array('Email')
        );

        $fields = $factory->getFields();

        $this->assertEquals($this->emailFields, array_keys($fields->dataFields()));

        $this->assertEquals($this->emailFields, array_keys($this->factory->getEmailFields()->dataFields()));
    }

    public function testCardTypes()
    {
        $types = $this->factory->getCardTypes();

        $this->assertInternalType('array', $types);

        $card = new \Omnipay\Common\CreditCard();

        $this->assertEquals(array_keys($card->getSupportedBrands()), array_keys($types));
    }

    public function testRequiredFields()
    {
        Config::modify()->merge(GatewayInfo::class, 'Dummy', array(
            'required_fields' => array(
                'billingAddress1',
                'city',
                'country',
                'email',
                'company'
            )
        ));

        Config::modify()->merge(GatewayInfo::class, 'PayPal_Express', array(
            'required_fields' => array(
                'billingAddress1',
                'city',
                'country',
                'email',
                'company'
            )
        ));

        $factory = new GatewayFieldsFactory('Dummy', array(
            'Card',
            'Billing',
            'Shipping',
            'Company',
            'Email'
        ));

        $fields = $factory->getFields();

        $defaults = array(
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
        );

        $this->assertEquals($this->factory->getFieldName($defaults), array_keys($fields->dataFields()));

        // Same procedure with offsite gateway should not return the CC fields

        $factory = new GatewayFieldsFactory('PayPal_Express', array(
            'Card',
            'Billing',
            'Shipping',
            'Company',
            'Email'
        ));

        $fields = $factory->getFields();

        $pxDefaults = array(
            'billingAddress1',
            'billingCity',
            'billingCountry',
            'shippingCity',
            'shippingCountry',
            'company',
            'email'
        );

        $this->assertEquals($this->factory->getFieldName($pxDefaults), array_keys($fields->dataFields()));
    }

    public function testRenamedFields()
    {
        Config::modify()->merge('SilverStripe\Omnipay\GatewayFieldsFactory', 'rename', array(
            'prefix' => 'prefix_',
            'name' => 'testName',
            'number' => 'testNumber',
            'expiryMonth' => 'testExpiryMonth',
            'expiryYear' => 'testExpiryYear',
            'Dummy' => array(
                'prefix' => 'dummy_',
                'number' => 'dummyCCnumber'
            )
        ));

        $factory = new GatewayFieldsFactory(null, array(
            'Card'
        ));

        $fields = $factory->getFields();

        $expected = array(
            'prefix_type',
            'prefix_testName',
            'prefix_testNumber',
            'prefix_startMonth',
            'prefix_startYear',
            'prefix_testExpiryMonth',
            'prefix_testExpiryYear',
            'prefix_cvv',
            'prefix_issueNumber'
        );

        $this->assertEquals($expected, array_keys($fields->dataFields()));

        $factory = new GatewayFieldsFactory('Dummy', array(
            'Card'
        ));

        $fields = $factory->getFields();

        $expected = array(
            'dummy_testName',
            'dummy_dummyCCnumber',
            'dummy_testExpiryMonth',
            'dummy_testExpiryYear',
            'dummy_cvv',
        );

        $this->assertEquals($expected, array_keys($fields->dataFields()));
    }

    public function testNormalizeFormData()
    {
        Config::modify()->set('SilverStripe\Omnipay\GatewayFieldsFactory', 'rename', array(
            'prefix' => 'prefix_',
            'name' => 'testName',
            'number' => 'testNumber',
            'expiryMonth' => 'testExpiryMonth',
            'expiryYear' => 'testExpiryYear',
            'Dummy' => array(
                'prefix' => 'dummy_',
                'number' => 'dummyCCnumber'
            )
        ));

        // Test global rename
        $factory = new GatewayFieldsFactory();
        $this->assertEquals(
            $factory->normalizeFormData(
                array(
                    'prefix_testName' => 'Reece Alexander',
                    'prefix_testNumber' => '4242424242424242',
                    'prefix_testExpiryMonth' => '11',
                    'prefix_testExpiryYear' => '2016',
                    'someOtherFormValue' => 'Should be unchanged',
                    // Ensure other fields are not affected by prefix change!
                    'prefix_prefixedValue' => 'Something'
                )
            ),
            array(
                'name' => 'Reece Alexander',
                'number' => '4242424242424242',
                'expiryMonth' => '11',
                'expiryYear' => '2016',
                'someOtherFormValue' => 'Should be unchanged',
                'prefix_prefixedValue' => 'Something'
            )
        );
        // Test gateway specific rename
        $factory = new GatewayFieldsFactory('Dummy');

        $this->assertEquals(
            $factory->normalizeFormData(
                array(
                    'dummy_testName' => 'Reece Alexander',
                    'dummy_dummyCCnumber' => '4242424242424242',
                    'dummy_testExpiryMonth' => '11',
                    'dummy_testExpiryYear' => '2016',
                    'someOtherFormValue' => 'Should be unchanged',
                    'dummy_prefixedValue' => 'Something'
                )
            ),
            array(
                'name' => 'Reece Alexander',
                'number' => '4242424242424242',
                'expiryMonth' => '11',
                'expiryYear' => '2016',
                'someOtherFormValue' => 'Should be unchanged',
                'dummy_prefixedValue' => 'Something'
            )
        );
    }
}
