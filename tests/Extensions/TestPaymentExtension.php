<?php

namespace SilverStripe\Omnipay\Tests\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Omnipay\Tests\Model\TestOrder;

class TestPaymentExtension extends DataExtension implements TestOnly
{
    private static $has_one = [
        'Test_Order' => TestOrder::class
    ];
}
