<?php
use Omnipay\Common\AbstractGateway;
use Omnipay\Common\GatewayFactory;
use SilverStripe\Omnipay\GatewayInfo;

class GatewayInfoTest extends SapphireTest
{
    protected $originalLocale;

    public function setUp()
    {
        parent::setUp();

        $this->originalLocale = i18n::get_locale();

        // Clear the allowed_gateways
        Config::inst()->remove('Payment', 'allowed_gateways');

        // clear settings for PaymentExpress_PxPay (don't let user configs bleed into tests)
        Config::inst()->remove('GatewayInfo', 'PaymentExpress_PxPay');

        Config::inst()->update('Payment', 'allowed_gateways', array(
            'PayPal_Express',
            'PaymentExpress_PxPay',
            'Dummy'
        ));

        i18n::get_translator('core')->getAdapter()->addTranslation(array(
            'Gateway.PayPal_Express' => 'PayPal Express EN',
            'Gateway.PaymentExpress_PxPay' => 'Px Pay Express EN',
            'Gateway.Dummy' => 'Dummy EN'
        ), 'en_US');

        i18n::get_translator('core')->getAdapter()->addTranslation(array(
            'Gateway.Dummy' => 'Dummy DE',
            'Gateway.PaymentExpress_PxPay' => '' // clear
        ), 'de_DE');

        Config::inst()->update('GatewayInfo', 'PaymentExpress_PxPay', array(
            'parameters' => array(
                'username' => 'EXAMPLEUSER',
                'password' => '235llgwxle4tol23l'
            ),
            'required_fields' => array(
                'name', 'number'
            ),
            'is_offsite' => true,
            'can_capture' => true,
            'can_refund' => 'full',
            'can_void' => false,
            'max_capture' => array(
                'percent' => '20',
                'amount' => '60'
            )
        ));
    }

    public function tearDown()
    {
        parent::tearDown();

        i18n::set_locale($this->originalLocale);
    }

    /**
     * Test the allowed_gateways config
     * @expectedException \SilverStripe\Omnipay\Exception\InvalidConfigurationException
     */
    public function testAllowedGateways()
    {
        $this->assertFalse(GatewayInfo::isSupported('Manual'), 'Manual isn\'t in the list of allowed gateways');

        $this->assertTrue(GatewayInfo::isSupported('PayPal_Express'), 'PayPal_Express is in the list of allowed gateways');

        Config::inst()->remove('Payment', 'allowed_gateways');
        // this should throw an InvalidConfigurationException (no gateways configured)
        GatewayInfo::getSupportedGateways();
    }

    /**
     * Test the niceTitle method
     * @expectedException PHPUnit_Framework_Error_Deprecated
     */
    public function testNiceTitle()
    {
        i18n::set_locale('en_US');

        $this->assertEquals(
            GatewayInfo::niceTitle('Dummy'),
            'Dummy EN',
            'Gateway info should return localized gateway name'
        );

        i18n::set_locale('de_DE');

        $this->assertEquals(
            GatewayInfo::niceTitle('Dummy'),
            'Dummy DE',
            'Gateway info should return localized gateway name'
        );

        $factory = new GatewayFactory();
        $gateway = $factory->create('PaymentExpress_PxPay');

        $this->assertEquals(
            GatewayInfo::niceTitle('PaymentExpress_PxPay'),
            $gateway->getName(),
            'niceTitle should return the gateway name if there\'s no localization present'
        );

        i18n::get_translator('core')->getAdapter()->addTranslation(array(
            'Payment.Dummy' => 'Dummy DE'
        ), 'de_DE');

        // Should generate a deprecation error
        GatewayInfo::niceTitle('Dummy');
    }

    /**
     * Test the return value of getSupportedGateways
     */
    public function testSupportedGateways()
    {
        i18n::set_locale('en_US');

        $this->assertEquals(
            GatewayInfo::getSupportedGateways(false),
            array(
                'PayPal_Express' => 'PayPal_Express',
                'PaymentExpress_PxPay' => 'PaymentExpress_PxPay',
                'Dummy' => 'Dummy'
            ),
            'When getting supported gateways without nice titles, keys and values in the array are identical'
        );

        $this->assertEquals(
            GatewayInfo::getSupportedGateways(true),
            array(
                'PayPal_Express' => 'PayPal Express EN',
                'PaymentExpress_PxPay' => 'Px Pay Express EN',
                'Dummy' => 'Dummy EN'
            ),
            'When getting supported gateways with nice titles, values must match translations'
        );
    }

    /**
     * Test the different ways a gateway can be detected as offsite
     */
    public function testIsOffsite()
    {
        Config::inst()->update('GatewayInfo', 'OffsiteGateway', array(
            'is_offsite' => true
        ));

        // this gateway doesn't implement `completePurchase`
        $this->assertFalse(GatewayInfo::isOffsite('\GatewayInfoTest_OnsiteGateway'));
        // this gateway does implement `completePurchase`
        $this->assertTrue(GatewayInfo::isOffsite('\GatewayInfoTest_OffsiteGateway'));
        // check a gateway that was configured to be offsite (purely based on config)
        $this->assertTrue(GatewayInfo::isOffsite('OffsiteGateway'));
    }

    /**
     * Test if the gateway is manual
     */
    public function testIsManual()
    {
        Config::inst()->update('GatewayInfo', 'Dummy', array(
            'is_manual' => true
        ));

        // should be manual, as it's explicitly configured
        $this->assertTrue(GatewayInfo::isManual('Dummy'));
        // should be manual, as it's actually a manual gateway
        $this->assertTrue(GatewayInfo::isManual('Manual'));
        // should not be manual
        $this->assertFalse(GatewayInfo::isManual('PaymentExpress_PxPay'));
    }

    /**
     * Test the use_authorize config
     */
    public function testUseAuthorize()
    {
        $this->assertFalse(
            GatewayInfo::shouldUseAuthorize('PaymentExpress_PxPay'),
            'PaymentExpress_PxPay wasn\'t configured to use authorize!'
        );

        $this->assertTrue(
            GatewayInfo::shouldUseAuthorize('Manual'),
            'Manual payments should always use authorize'
        );

        // update config
        Config::inst()->update('GatewayInfo', 'PaymentExpress_PxPay', array(
            'use_authorize' => true
        ));

        $this->assertTrue(
            GatewayInfo::shouldUseAuthorize('PaymentExpress_PxPay'),
            'PaymentExpress_PxPay was configured to use authorize!'
        );
    }

    /**
     * Test the use_async_notification config
     */
    public function testUseAsyncNotification()
    {
        $this->assertFalse(
            GatewayInfo::shouldUseAsyncNotifications('PaymentExpress_PxPay'),
            'PaymentExpress_PxPay wasn\'t configured to use async notifications!'
        );

        // update config on manual gateway (should be ignored!)
        Config::inst()->update('GatewayInfo', 'Manual', array(
            'use_async_notification' => true
        ));

        $this->assertFalse(
            GatewayInfo::shouldUseAsyncNotifications('Manual'),
            'Manual payments should never use asnyc notifications '
        );

        // update config of existing (non manual) gateway
        Config::inst()->update('GatewayInfo', 'PaymentExpress_PxPay', array(
            'use_async_notification' => true
        ));

        $this->assertTrue(
            GatewayInfo::shouldUseAsyncNotifications('PaymentExpress_PxPay'),
            'PaymentExpress_PxPay was configured to use async notifications!'
        );
    }

    /**
     * Test the token_key config
     */
    public function testTokenKey()
    {
        $this->assertEquals(
            GatewayInfo::getTokenKey('PaymentExpress_PxPay', 'TOKEN'),
            'TOKEN',
            'getTokenKey should return the default token if none was configured'
        );

        // update config of existing gateway
        Config::inst()->update('GatewayInfo', 'PaymentExpress_PxPay', array(
            'token_key' => 'MyTokenKey'
        ));

        $this->assertEquals(
            GatewayInfo::getTokenKey('PaymentExpress_PxPay', 'TOKEN'),
            'MyTokenKey',
            'getTokenKey should return the configured token if one was configured'
        );

        // update config to an invalid token key (not a string)
        Config::inst()->update('GatewayInfo', 'PaymentExpress_PxPay', array(
            'token_key' => 12
        ));

        $this->assertEquals(
            GatewayInfo::getTokenKey('PaymentExpress_PxPay', 'TOKEN'),
            'TOKEN',
            'getTokenKey should return the default token if config was invalid'
        );
    }

    /**
     * Test required fields
     */
    public function testRequiredFields()
    {
        $this->assertEquals(
            GatewayInfo::requiredFields('\GatewayInfoTest_OnsiteGateway'),
            array('name', 'number', 'expiryMonth', 'expiryYear', 'cvv'),
            'Onsite gateway must have at least these default required fields'
        );

        $this->assertEquals(
            GatewayInfo::requiredFields('PaymentExpress_PxPay'),
            array('name', 'number'),
            'Required fields must match the ones defined in config'
        );

        Config::inst()->update('GatewayInfo', '\GatewayInfoTest_OnsiteGateway', array(
            'required_fields' => array('important', 'very_important', 'cvv')
        ));

        $this->assertEquals(
            GatewayInfo::requiredFields('\GatewayInfoTest_OnsiteGateway'),
            array('important', 'very_important', 'cvv', 'name', 'number', 'expiryMonth', 'expiryYear'),
            'Onsite gateway must merge default and defined required fields'
        );

        // test with a gateway that doesn't have required fields
        $this->assertInternalType(
            'array',
            GatewayInfo::requiredFields('Dummy'),
            'Required fields should always return at least an array'
        );
    }

    public function testMaxCapture()
    {
        // Without setting the config, the max Excess amount should always be 0
        $this->assertEquals(0, GatewayInfo::maxExcessCaptureAmount('Dummy'));
        $this->assertEquals(0, GatewayInfo::maxExcessCapturePercent('Dummy'));

        // Check existing configuration
        $this->assertEquals(60, GatewayInfo::maxExcessCaptureAmount('PaymentExpress_PxPay'));
        $this->assertEquals(20, GatewayInfo::maxExcessCapturePercent('PaymentExpress_PxPay'));

        // set only max amount
        Config::inst()->update('GatewayInfo', 'Dummy', array('max_capture' => '12'));

        $this->assertEquals(12, GatewayInfo::maxExcessCaptureAmount('Dummy'));
        $this->assertEquals(-1, GatewayInfo::maxExcessCapturePercent('Dummy'));

        // set only percentage
        Config::inst()->update('GatewayInfo', 'Dummy', array('max_capture' => '15%'));

        $this->assertEquals(-1, GatewayInfo::maxExcessCaptureAmount('Dummy'));
        $this->assertEquals(15, GatewayInfo::maxExcessCapturePercent('Dummy'));

        // invalid values
        Config::inst()->update('GatewayInfo', 'Dummy', array('max_capture' => '-1'));
        $this->assertEquals(0, GatewayInfo::maxExcessCaptureAmount('Dummy'));
        $this->assertEquals(0, GatewayInfo::maxExcessCapturePercent('Dummy'));

        Config::inst()->update('GatewayInfo', 'Dummy', array('max_capture' => '-20%'));
        $this->assertEquals(0, GatewayInfo::maxExcessCaptureAmount('Dummy'));
        $this->assertEquals(0, GatewayInfo::maxExcessCapturePercent('Dummy'));

        Config::inst()->update('GatewayInfo', 'Dummy', array('max_capture' => 'test'));
        $this->assertEquals(0, GatewayInfo::maxExcessCaptureAmount('Dummy'));
        $this->assertEquals(0, GatewayInfo::maxExcessCapturePercent('Dummy'));

        // both values
        Config::inst()->remove('GatewayInfo', 'Dummy');
        Config::inst()->update('GatewayInfo', 'Dummy', array('max_capture' => array(
            'amount' => 80,
            'percent' => 14
        )));
        $this->assertEquals(80, GatewayInfo::maxExcessCaptureAmount('Dummy'));
        $this->assertEquals(14, GatewayInfo::maxExcessCapturePercent('Dummy'));

        Config::inst()->update('GatewayInfo', 'Dummy', array('max_capture' => array(
            'amount' => 60,
            'percent' => '30%'
        )));
        $this->assertEquals(60, GatewayInfo::maxExcessCaptureAmount('Dummy'));
        $this->assertEquals(30, GatewayInfo::maxExcessCapturePercent('Dummy'));

        // invalid values
        Config::inst()->update('GatewayInfo', 'Dummy', array('max_capture' => array(
            'amount' => -30,
            'percent' => '-10%'
        )));
        $this->assertEquals(0, GatewayInfo::maxExcessCaptureAmount('Dummy'));
        $this->assertEquals(0, GatewayInfo::maxExcessCapturePercent('Dummy'));

        // Amount values per currency
        Config::inst()->remove('GatewayInfo', 'Dummy');
        Config::inst()->update('GatewayInfo', 'Dummy', array('max_capture' => array(
            'amount' => array(
                'USD' => 80,
                'EUR' => '70%', // invalid value, should result in 0
                'TRY' => 224,
                'GBP' => -10 // invalid value, should result in 0
            ),
            'percent' => '20%'
        )));
        $this->assertEquals(0, GatewayInfo::maxExcessCaptureAmount('Dummy'));
        $this->assertEquals(80, GatewayInfo::maxExcessCaptureAmount('Dummy', 'USD'));
        $this->assertEquals(0, GatewayInfo::maxExcessCaptureAmount('Dummy', 'EUR'));
        $this->assertEquals(224, GatewayInfo::maxExcessCaptureAmount('Dummy', 'TRY'));
        $this->assertEquals(0, GatewayInfo::maxExcessCaptureAmount('Dummy', 'GBP'));
        $this->assertEquals(20, GatewayInfo::maxExcessCapturePercent('Dummy'));
    }

    public function testAllowedMethods()
    {
        // a gateway without explicitly disabling void, capture and refund should allow per default
        $this->assertTrue(GatewayInfo::allowCapture('Dummy'));
        $this->assertTrue(GatewayInfo::allowPartialCapture('Dummy'));
        $this->assertTrue(GatewayInfo::allowRefund('Dummy'));
        $this->assertTrue(GatewayInfo::allowPartialRefund('Dummy'));
        $this->assertTrue(GatewayInfo::allowVoid('Dummy'));

        // check if the config is respected
        $this->assertTrue(GatewayInfo::allowCapture('PaymentExpress_PxPay'));
        $this->assertTrue(GatewayInfo::allowPartialCapture('PaymentExpress_PxPay'));
        $this->assertTrue(GatewayInfo::allowRefund('PaymentExpress_PxPay'));
        $this->assertFalse(GatewayInfo::allowPartialRefund('PaymentExpress_PxPay'));
        $this->assertFalse(GatewayInfo::allowVoid('PaymentExpress_PxPay'));

        // check with explicit values
        Config::inst()->update('GatewayInfo', 'PaymentExpress_PxPay', array(
            'can_capture' => 'full',
            'can_refund' => 'off',
            'can_void' => 'off'
        ));

        $this->assertTrue(GatewayInfo::allowCapture('PaymentExpress_PxPay'));
        $this->assertFalse(GatewayInfo::allowPartialCapture('PaymentExpress_PxPay'));
        $this->assertFalse(GatewayInfo::allowRefund('PaymentExpress_PxPay'));
        $this->assertFalse(GatewayInfo::allowPartialRefund('PaymentExpress_PxPay'));
        $this->assertFalse(GatewayInfo::allowVoid('PaymentExpress_PxPay'));

        Config::inst()->update('GatewayInfo', 'PaymentExpress_PxPay', array(
            'can_capture' => 'partial',
            'can_refund' => 'full',
            'can_void' => 'false'
        ));

        $this->assertTrue(GatewayInfo::allowCapture('PaymentExpress_PxPay'));
        $this->assertTrue(GatewayInfo::allowPartialCapture('PaymentExpress_PxPay'));
        $this->assertTrue(GatewayInfo::allowRefund('PaymentExpress_PxPay'));
        $this->assertFalse(GatewayInfo::allowPartialRefund('PaymentExpress_PxPay'));
        $this->assertFalse(GatewayInfo::allowVoid('PaymentExpress_PxPay'));

        // check with true/false
        Config::inst()->update('GatewayInfo', 'PaymentExpress_PxPay', array(
            'can_capture' => false,
            'can_refund' => true,
            'can_void' => true
        ));

        $this->assertFalse(GatewayInfo::allowCapture('PaymentExpress_PxPay'));
        $this->assertFalse(GatewayInfo::allowPartialCapture('PaymentExpress_PxPay'));
        $this->assertTrue(GatewayInfo::allowRefund('PaymentExpress_PxPay'));
        $this->assertTrue(GatewayInfo::allowPartialRefund('PaymentExpress_PxPay'));
        $this->assertTrue(GatewayInfo::allowVoid('PaymentExpress_PxPay'));

        // check with "truthy" and "falsy" values
        Config::inst()->update('GatewayInfo', 'PaymentExpress_PxPay', array(
            'can_capture' => '0',
            'can_refund' => '1',
            'can_void' => '1'
        ));

        $this->assertFalse(GatewayInfo::allowCapture('PaymentExpress_PxPay'));
        $this->assertFalse(GatewayInfo::allowPartialCapture('PaymentExpress_PxPay'));
        $this->assertTrue(GatewayInfo::allowRefund('PaymentExpress_PxPay'));
        $this->assertTrue(GatewayInfo::allowPartialRefund('PaymentExpress_PxPay'));
        $this->assertTrue(GatewayInfo::allowVoid('PaymentExpress_PxPay'));
    }
}

class GatewayInfoTest_OnsiteGateway extends AbstractGateway implements TestOnly
{
    public function getName()
    {
        return 'GatewayInfoTest_OnsiteGateway';
    }

    public function getDefaultParameters()
    {
        return array();
    }

    public function purchase(array $parameters = array())
    {
    }
}

class GatewayInfoTest_OffsiteGateway extends AbstractGateway implements TestOnly
{

    public function getName()
    {
        return 'GatewayInfoTest_OffsiteGateway';
    }

    public function getDefaultParameters()
    {
        return array();
    }

    public function purchase(array $parameters = array())
    {
    }

    public function completePurchase(array $options = array())
    {
    }
}
