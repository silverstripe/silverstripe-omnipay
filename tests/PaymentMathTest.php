<?php

namespace SilverStripe\Omnipay\Tests;

use SilverStripe\Omnipay\PaymentMath;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;

/**
 * Test payment math operations
 */
class PaymentMathTest extends SapphireTest
{
    public function setUp()
    {
        parent::setUp();
        Config::modify()->set('SilverStripe\Omnipay\PaymentMath', 'precision', 2);
        Config::modify()->set('SilverStripe\Omnipay\PaymentMath', 'useBcMath', true);
    }

    public function testPrecision()
    {
        if (!function_exists('bcsub')) {
            $this->markTestIncomplete('BCMath extension not available');
            return;
        }

        Config::modify()->set('SilverStripe\Omnipay\PaymentMath', 'precision', -1);
        $this->assertEquals('99', PaymentMath::subtract('100.00', '0.1'));
        $this->assertEquals('0', PaymentMath::add('0.273', '0.226'));

        Config::modify()->set('SilverStripe\Omnipay\PaymentMath', 'precision', 0);
        $this->assertEquals('99', PaymentMath::subtract('100.00', '0.1'));
        $this->assertEquals('0', PaymentMath::add('0.273', '0.226'));

        Config::modify()->set('SilverStripe\Omnipay\PaymentMath', 'precision', 1);
        $this->assertEquals('99.9', PaymentMath::subtract('100.00', '0.1'));
        $this->assertEquals('0.4', PaymentMath::add('0.273', '0.226'));

        Config::modify()->set('SilverStripe\Omnipay\PaymentMath', 'precision', 2);
        $this->assertEquals('99.90', PaymentMath::subtract('100.00', '0.1'));
        $this->assertEquals('0.49', PaymentMath::add('0.273', '0.226'));

        Config::modify()->set('SilverStripe\Omnipay\PaymentMath', 'precision', 15);
        $this->assertEquals('99.900000000000000', PaymentMath::subtract('100.00', '0.1'));
        $this->assertEquals('0.499000000000000', PaymentMath::add('0.273', '0.226'));
    }

    public function testPrecisionFloat()
    {
        Config::modify()->set('SilverStripe\Omnipay\PaymentMath', 'useBcMath', false);

        Config::modify()->set('SilverStripe\Omnipay\PaymentMath', 'precision', -1);
        $this->assertEquals('99', PaymentMath::subtract('100.00', '0.1'));
        $this->assertEquals('0', PaymentMath::add('0.273', '0.226'));

        Config::modify()->set('SilverStripe\Omnipay\PaymentMath', 'precision', 0);
        $this->assertEquals('99', PaymentMath::subtract('100.00', '0.1'));
        $this->assertEquals('0', PaymentMath::add('0.273', '0.226'));

        Config::modify()->set('SilverStripe\Omnipay\PaymentMath', 'precision', 1);
        $this->assertEquals('99.9', PaymentMath::subtract('100.00', '0.1'));
        $this->assertEquals('0.4', PaymentMath::add('0.273', '0.226'));

        Config::modify()->set('SilverStripe\Omnipay\PaymentMath', 'precision', 2);
        $this->assertEquals('99.90', PaymentMath::subtract('100.00', '0.1'));
        $this->assertEquals('0.49', PaymentMath::add('0.273', '0.226'));

        Config::modify()->set('SilverStripe\Omnipay\PaymentMath', 'precision', 15);
        $this->assertEquals('99.900000000000000', PaymentMath::subtract('100.00', '0.1'));
        $this->assertEquals('0.499000000000000', PaymentMath::add('0.273', '0.226'));
    }

    public function testSubtraction()
    {
        if (!function_exists('bcsub')) {
            $this->markTestIncomplete('BCMath extension not available');
            return;
        }

        $result = PaymentMath::subtract('100.00', '3.6');
        $this->assertEquals('96.40', $result);

        $result = PaymentMath::subtract('100.00', '54.001');
        $this->assertEquals('45.99', $result);

        Config::modify()->set('SilverStripe\Omnipay\PaymentMath', 'precision', 4);

        $result = PaymentMath::subtract('100.00', '3.6');
        $this->assertEquals('96.4000', $result);

        $result = PaymentMath::subtract('100.00', '54.001');
        $this->assertEquals('45.9990', $result);
    }

    public function testSubtractionFloat()
    {
        Config::modify()->set('SilverStripe\Omnipay\PaymentMath', 'useBcMath', false);

        $result = PaymentMath::subtract('100.00', '3.6');
        $this->assertEquals('96.40', $result);

        $result = PaymentMath::subtract('100.00', '54.001');
        $this->assertEquals('45.99', $result);

        Config::modify()->set('SilverStripe\Omnipay\PaymentMath', 'precision', 4);

        $result = PaymentMath::subtract('100.00', '3.6');
        $this->assertEquals('96.4000', $result);

        $result = PaymentMath::subtract('100.00', '54.001');
        $this->assertEquals('45.9990', $result);
    }

    public function testAddition()
    {
        if (!function_exists('bcadd')) {
            $this->markTestIncomplete('BCMath extension not available');
            return;
        }

        $result = PaymentMath::add('3.6', '80.40');
        $this->assertEquals('84.00', $result);

        $result = PaymentMath::add('100000.001', '0.1');
        $this->assertEquals('100000.10', $result);

        Config::modify()->set('SilverStripe\Omnipay\PaymentMath', 'precision', 4);

        $result = PaymentMath::add('3.6', '80.40');
        $this->assertEquals('84.0000', $result);

        $result = PaymentMath::add('100000.001', '0.1');
        $this->assertEquals('100000.1010', $result);
    }

    public function testAdditionFloat()
    {
        Config::modify()->set('SilverStripe\Omnipay\PaymentMath', 'useBcMath', false);

        $result = PaymentMath::add('3.6', '80.40');
        $this->assertEquals('84.00', $result);

        $result = PaymentMath::add('100000.001', '0.1');
        $this->assertEquals('100000.10', $result);

        Config::modify()->set('SilverStripe\Omnipay\PaymentMath', 'precision', 4);

        $result = PaymentMath::add('3.6', '80.40');
        $this->assertEquals('84.0000', $result);

        $result = PaymentMath::add('100000.001', '0.1');
        $this->assertEquals('100000.1010', $result);
    }

    public function testMultiply()
    {
        $this->assertEquals('0.00', PaymentMath::multiply('0.0001', '10'));
        $this->assertEquals('19.99', PaymentMath::multiply('0.0199999', '1000'));
        $this->assertEquals('10.00', PaymentMath::multiply('100.05', '0.1'));
        $this->assertEquals('-10.00', PaymentMath::multiply('100.05', '-0.1'));
        $this->assertEquals('912345678000000.00', PaymentMath::multiply('912345678', '1000000'));
        $this->assertEquals('912345678000000000.00', PaymentMath::multiply('912345678', '1000000000'));

        Config::modify()->set('SilverStripe\Omnipay\PaymentMath', 'precision', 4);

        $this->assertEquals('0.0010', PaymentMath::multiply('0.0001', '10'));
        $this->assertEquals('19.9999', PaymentMath::multiply('0.0199999', '1000'));
        $this->assertEquals('10.0050', PaymentMath::multiply('100.05', '0.1'));
        $this->assertEquals('-10.0050', PaymentMath::multiply('100.05', '-0.1'));
        $this->assertEquals('912345678000000.0000', PaymentMath::multiply('912345678', '1000000'));
        $this->assertEquals('912345678000000000.0000', PaymentMath::multiply('912345678', '1000000000'));
    }

    public function testMultiplyFloat()
    {
        Config::modify()->set('SilverStripe\Omnipay\PaymentMath', 'useBcMath', false);

        $this->assertEquals('0.00', PaymentMath::multiply('0.0001', '10'));
        $this->assertEquals('19.99', PaymentMath::multiply('0.0199999', '1000'));
        $this->assertEquals('10.00', PaymentMath::multiply('100.05', '0.1'));
        $this->assertEquals('-10.00', PaymentMath::multiply('100.05', '-0.1'));
        $this->assertEquals('912345678000000.00', PaymentMath::multiply('912345678', '1000000'));
        // this will fail due to integer overflow
        $this->assertNotEquals('912345678000000000.00', PaymentMath::multiply('912345678', '1000000000'));

        Config::modify()->set('SilverStripe\Omnipay\PaymentMath', 'precision', 4);

        $this->assertEquals('0.0010', PaymentMath::multiply('0.0001', '10'));
        $this->assertEquals('19.9999', PaymentMath::multiply('0.0199999', '1000'));
        $this->assertEquals('10.0050', PaymentMath::multiply('100.05', '0.1'));
        $this->assertEquals('-10.0050', PaymentMath::multiply('100.05', '-0.1'));
        $this->assertEquals('912345678000000.0000', PaymentMath::multiply('912345678', '1000000'));
        // this will fail due to integer overflow
        $this->assertNotEquals('912345678000000000.0000', PaymentMath::multiply('912345678', '1000000000'));
    }

    public function testCompare()
    {
        $this->assertEquals(1, PaymentMath::compare('10', '0'));
        $this->assertEquals(-1, PaymentMath::compare('-10', '0'));
        $this->assertEquals(0, PaymentMath::compare('1000', '1000'));

        $this->assertEquals(-1, PaymentMath::compare('1', '1.01'));
        $this->assertEquals(1, PaymentMath::compare('1.02', '1.01'));
        // equal because of precision
        $this->assertEquals(0, PaymentMath::compare('1.0001', '1.0002'));
        $this->assertEquals(0, PaymentMath::compare('1.11112', '1.11113'));

        Config::modify()->set('SilverStripe\Omnipay\PaymentMath', 'precision', 4);

        $this->assertEquals(1, PaymentMath::compare('10000000', '-1000000'));
        $this->assertEquals(-1, PaymentMath::compare('-10', '-5'));
        $this->assertEquals(0, PaymentMath::compare('-1000', '-1000'));

        $this->assertEquals(-1, PaymentMath::compare('1', '1.01'));
        $this->assertEquals(1, PaymentMath::compare('1.02', '1.01'));

        $this->assertEquals(1, PaymentMath::compare('1.0001', '1'));
        $this->assertEquals(-1, PaymentMath::compare('1.0001', '1.0002'));
        // equal because of precision
        $this->assertEquals(0, PaymentMath::compare('1.11112', '1.11113'));
    }

    public function testCompareFloat()
    {
        Config::modify()->set('SilverStripe\Omnipay\PaymentMath', 'useBcMath', false);

        $this->assertEquals(1, PaymentMath::compare('10', '0'));
        $this->assertEquals(-1, PaymentMath::compare('-10', '0'));
        $this->assertEquals(0, PaymentMath::compare('1000', '1000'));

        $this->assertEquals(-1, PaymentMath::compare('1', '1.01'));
        $this->assertEquals(1, PaymentMath::compare('1.02', '1.01'));
        // equal because of precision
        $this->assertEquals(0, PaymentMath::compare('1.0001', '1.0002'));
        $this->assertEquals(0, PaymentMath::compare('1.11112', '1.11113'));

        Config::modify()->set('SilverStripe\Omnipay\PaymentMath', 'precision', 4);

        $this->assertEquals(1, PaymentMath::compare('10000000', '-1000000'));
        $this->assertEquals(-1, PaymentMath::compare('-10', '-5'));
        $this->assertEquals(0, PaymentMath::compare('-1000', '-1000'));

        $this->assertEquals(-1, PaymentMath::compare('1', '1.01'));
        $this->assertEquals(1, PaymentMath::compare('1.02', '1.01'));

        $this->assertEquals(1, PaymentMath::compare('1.0001', '1'));
        $this->assertEquals(-1, PaymentMath::compare('1.0001', '1.0002'));
        // equal because of precision
        $this->assertEquals(0, PaymentMath::compare('1.11112', '1.11113'));
    }
}
