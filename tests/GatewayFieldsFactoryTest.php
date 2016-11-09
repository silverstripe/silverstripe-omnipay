<?php

use SilverStripe\Omnipay\GatewayFieldsFactory;

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
        $this->factory =  new GatewayFieldsFactory(
            null, array(
            'Card',
            'Billing',
            'Shipping',
            'Company',
            'Email'
        ));

        // caters for custom field names so that tests pass even if user has defined custom names
        $fieldSets = array(
            &$this->ccFields,
            &$this->billingFields,
            &$this->shippingFields,
            &$this->companyFields,
            &$this->emailFields,
        );
        foreach ($fieldSets as &$fieldSet) {
            foreach ($fieldSet as &$field) {
                $field = $this->factory->getFieldName($field);
            }
        }
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
            null, array('Card')
        );

        $fields = $factory->getFields();

        $this->assertEquals($this->ccFields, array_keys($fields->dataFields()));

        $this->assertEquals($this->ccFields, array_keys($this->factory->getCardFields()->dataFields()));
    }

    public function testBillingFields()
    {
        // Create a gateway-factory without a gateway
        $factory = new GatewayFieldsFactory(
            null, array('Billing')
        );

        $fields = $factory->getFields();

        $this->assertEquals($this->billingFields, array_keys($fields->dataFields()));

        $this->assertEquals($this->billingFields, array_keys($this->factory->getBillingFields()->dataFields()));
    }

    public function testShippingFields()
    {
        // Create a gateway-factory without a gateway
        $factory = new GatewayFieldsFactory(
            null, array('Shipping')
        );

        $fields = $factory->getFields();

        $this->assertEquals($this->shippingFields, array_keys($fields->dataFields()));

        $this->assertEquals($this->shippingFields, array_keys($this->factory->getShippingFields()->dataFields()));
    }

    public function testCompanyFields()
    {
        // Create a gateway-factory without a gateway
        $factory = new GatewayFieldsFactory(
            null, array('Company')
        );

        $fields = $factory->getFields();

        $this->assertEquals($this->companyFields, array_keys($fields->dataFields()));

        $this->assertEquals($this->companyFields, array_keys($this->factory->getCompanyFields()->dataFields()));
    }

    public function testEmailFields()
    {
        // Create a gateway-factory without a gateway
        $factory = new GatewayFieldsFactory(
            null, array('Email')
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
        Config::inst()->update('GatewayInfo', 'Dummy', array(
            'required_fields' => array(
                'billingAddress1',
                'city',
                'country',
                'email',
                'company'
            )
        ));

        Config::inst()->update('GatewayInfo', 'PayPal_Express', array(
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

        $this->renameWalk($defaults);

        $this->assertEquals($defaults, array_keys($fields->dataFields()));

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

        $this->renameWalk($pxDefaults);

        $this->assertEquals($pxDefaults, array_keys($fields->dataFields()));
    }

    function renameWalk(&$array) {
        return $array = array_map(
            function ($name) {
                return $this->factory->getFieldName($name);
            },
            $array
        );
    }
}
