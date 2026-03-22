<?php

namespace SilverStripe\Omnipay\Tests\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Omnipay\Tests\Model\TestOrder;

class TestPaymentExtension extends Extension implements TestOnly
{
    private static $has_one = [
        'Test_Order' => TestOrder::class
    ];
}
