<?php

namespace SilverStripe\Omnipay\Tests\Model;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Test double for an order that accepts payments.
 * {@see Payable} is applied via {@see PayableTest::$required_extensions} so it is registered under test config.
 */
class TestOrder extends DataObject implements TestOnly
{
    private static $table_name = 'Omnipay_TestOrder';
}
